<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

try {
    $db = new PDO('sqlite:' . __DIR__ . '/lv_data.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec("PRAGMA journal_mode=WAL");
} catch(Exception $e) {
    echo json_encode(['ok'=>false,'error'=>'DB chyba']); exit;
}

$db->exec("CREATE TABLE IF NOT EXISTS users (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  email TEXT UNIQUE NOT NULL,
  password TEXT NOT NULL,
  verified INTEGER DEFAULT 0,
  subscription INTEGER DEFAULT 0,
  is_admin INTEGER DEFAULT 0,
  created_at TEXT DEFAULT (datetime('now'))
)");
$db->exec("CREATE TABLE IF NOT EXISTS codes (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  email TEXT NOT NULL, code TEXT NOT NULL, expires INTEGER NOT NULL
)");
$db->exec("CREATE TABLE IF NOT EXISTS calcs (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL, name TEXT NOT NULL,
  data TEXT NOT NULL, result TEXT NOT NULL,
  created_at TEXT DEFAULT (datetime('now'))
)");

if (!$db->query("SELECT id FROM users WHERE is_admin=1 LIMIT 1")->fetch()) {
    $db->prepare("INSERT OR IGNORE INTO users (email,password,verified,subscription,is_admin) VALUES (?,?,1,1,1)")
       ->execute(['admin@livenia.eu', password_hash('Livenia2025admin', PASSWORD_DEFAULT)]);
}

function ok($d=[])  { echo json_encode(array_merge(['ok'=>true],$d), JSON_UNESCAPED_UNICODE); exit; }
function fail($msg) { echo json_encode(['ok'=>false,'error'=>$msg], JSON_UNESCAPED_UNICODE); exit; }
function genCode()  {
    $c='ABCDEFGHJKMNPQRSTUVWXYZ23456789'; $r='';
    for($i=0;$i<4;$i++) $r.=$c[random_int(0,strlen($c)-1)];
    return $r;
}

$a = $_POST['action'] ?? $_GET['action'] ?? '';

if ($a==='register') {
    $email = strtolower(trim($_POST['email']??''));
    $pw    = $_POST['password']??'';
    if (!filter_var($email,FILTER_VALIDATE_EMAIL)) fail('Neplatná e-mailová adresa');
    if (!$pw) fail('Zadejte heslo');
    $s=$db->prepare("SELECT id,verified FROM users WHERE email=?"); $s->execute([$email]); $u=$s->fetch(PDO::FETCH_ASSOC);
    if ($u && $u['verified']) fail('Tento e-mail je již zaregistrován');
    $hash=password_hash($pw,PASSWORD_DEFAULT);
    if ($u) { $db->prepare("UPDATE users SET password=? WHERE email=?")->execute([$hash,$email]); }
    else    { $db->prepare("INSERT INTO users (email,password) VALUES (?,?)")->execute([$email,$hash]); }
    $code=genCode();
    $db->prepare("DELETE FROM codes WHERE email=?")->execute([$email]);
    $db->prepare("INSERT INTO codes (email,code,expires) VALUES (?,?,?)")->execute([$email,$code,time()+3600]);
    $subj='=?UTF-8?B?'.base64_encode('Livenia – ověřovací kód').'?=';
    $body="Dobrý den,\n\nVáš ověřovací kód pro Livenia je:\n\n    $code\n\nKód je platný 1 hodinu.\n\nLivenia tým";
    $hdrs="From: Livenia <livenia@email.cz>\r\nContent-Type: text/plain; charset=UTF-8\r\n";
    @mail($email,$subj,$body,$hdrs);
    ok();
}

if ($a==='verify') {
    $email=strtolower(trim($_POST['email']??''));
    $code=strtoupper(trim($_POST['code']??''));
    $s=$db->prepare("SELECT id FROM codes WHERE email=? AND code=? AND expires>?"); $s->execute([$email,$code,time()]);
    if (!$s->fetch()) fail('Neplatný nebo prošlý kód');
    $db->prepare("UPDATE users SET verified=1 WHERE email=?")->execute([$email]);
    $db->prepare("DELETE FROM codes WHERE email=?")->execute([$email]);
    $s=$db->prepare("SELECT * FROM users WHERE email=?"); $s->execute([$email]); $u=$s->fetch(PDO::FETCH_ASSOC);
    $_SESSION['uid']=$u['id']; $_SESSION['email']=$u['email']; $_SESSION['adm']=(bool)$u['is_admin'];
    ok(['user'=>['email'=>$u['email'],'subscription'=>(bool)$u['subscription'],'is_admin'=>(bool)$u['is_admin']]]);
}

if ($a==='login') {
    $email=strtolower(trim($_POST['email']??''));
    $pw=$_POST['password']??'';
    $s=$db->prepare("SELECT * FROM users WHERE email=?"); $s->execute([$email]); $u=$s->fetch(PDO::FETCH_ASSOC);
    if (!$u||!password_verify($pw,$u['password'])) fail('Nesprávný e-mail nebo heslo');
    if (!$u['verified']) {
        echo json_encode(['ok'=>false,'error'=>'Účet čeká na ověření e-mailu.','needs_verify'=>true,'email'=>$email],JSON_UNESCAPED_UNICODE); exit;
    }
    $_SESSION['uid']=$u['id']; $_SESSION['email']=$u['email']; $_SESSION['adm']=(bool)$u['is_admin'];
    ok(['user'=>['email'=>$u['email'],'subscription'=>(bool)$u['subscription'],'is_admin'=>(bool)$u['is_admin']]]);
}

if ($a==='logout')        { session_destroy(); ok(); }

if ($a==='check_session') {
    if (!isset($_SESSION['uid'])) { echo json_encode(['ok'=>false]); exit; }
    $s=$db->prepare("SELECT subscription,is_admin FROM users WHERE id=?"); $s->execute([$_SESSION['uid']]); $u=$s->fetch(PDO::FETCH_ASSOC);
    ok(['user'=>['email'=>$_SESSION['email'],'subscription'=>(bool)$u['subscription'],'is_admin'=>(bool)$u['is_admin']]]);
}

if ($a==='save_calc') {
    if (!isset($_SESSION['uid'])) fail('Nejste přihlášeni');
    $uid=$_SESSION['uid'];
    $s=$db->prepare("SELECT subscription FROM users WHERE id=?"); $s->execute([$uid]); $u=$s->fetch(PDO::FETCH_ASSOC);
    if (!$u['subscription']) {
        $c=$db->prepare("SELECT COUNT(*) FROM calcs WHERE user_id=?"); $c->execute([$uid]);
        if ($c->fetchColumn()>=1) { echo json_encode(['ok'=>false,'error'=>'Bez předplatného lze uložit pouze 1 výpočet.','needs_subscription'=>true],JSON_UNESCAPED_UNICODE); exit; }
    }
    $name=trim($_POST['name']??'Výpočet '.date('d.m.Y H:i'));
    $db->prepare("INSERT INTO calcs (user_id,name,data,result) VALUES (?,?,?,?)")->execute([$uid,$name,$_POST['data']??'',$_POST['result']??'']);
    ok(['id'=>$db->lastInsertId()]);
}

if ($a==='get_calcs') {
    if (!isset($_SESSION['uid'])) fail('Nejste přihlášeni');
    $s=$db->prepare("SELECT id,name,result,created_at FROM calcs WHERE user_id=? ORDER BY created_at DESC"); $s->execute([$_SESSION['uid']]);
    ok(['calcs'=>$s->fetchAll(PDO::FETCH_ASSOC)]);
}

if ($a==='load_calc') {
    if (!isset($_SESSION['uid'])) fail('Nejste přihlášeni');
    $s=$db->prepare("SELECT * FROM calcs WHERE id=? AND user_id=?"); $s->execute([(int)($_POST['id']??0),$_SESSION['uid']]);
    $c=$s->fetch(PDO::FETCH_ASSOC); if (!$c) fail('Nenalezeno');
    ok(['calc'=>$c]);
}

if ($a==='delete_calc') {
    if (!isset($_SESSION['uid'])) fail('Nejste přihlášeni');
    $db->prepare("DELETE FROM calcs WHERE id=? AND user_id=?")->execute([(int)($_POST['id']??0),$_SESSION['uid']]);
    ok();
}

if ($a==='admin_users') {
    if (empty($_SESSION['adm'])) fail('Přístup odepřen');
    $rows=$db->query("SELECT u.id,u.email,u.verified,u.subscription,u.created_at,(SELECT COUNT(*) FROM calcs WHERE user_id=u.id) as calcs FROM users u WHERE u.is_admin=0 ORDER BY u.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
    ok(['users'=>$rows]);
}

if ($a==='admin_toggle_sub') {
    if (empty($_SESSION['adm'])) fail('Přístup odepřen');
    $id=(int)($_POST['id']??0);
    $db->prepare("UPDATE users SET subscription=1-subscription WHERE id=? AND is_admin=0")->execute([$id]);
    $s=$db->prepare("SELECT subscription FROM users WHERE id=?"); $s->execute([$id]);
    ok(['subscription'=>(bool)$s->fetch()['subscription']]);
}

fail('Neznámá akce');
?>