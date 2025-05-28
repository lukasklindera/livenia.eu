// Plynulé skrolování při kliknutí na navigaci nebo šipku dolů
document.querySelectorAll('nav a, .scroll-down img, #domu a').forEach(anchor => {
    anchor.addEventListener('click', function(e) {
        e.preventDefault();
        let targetId = this.getAttribute('href'); 
        
        // Pokud kliknu na "Domů", nastavíme targetId na #home
        if (!targetId || targetId === "#") {
            targetId = "#onas";
        }

        // Ověříme, zda sekce existuje a provedeme plynulé skrolování
        const targetElement = document.querySelector(targetId);
        if (targetElement) {
            targetElement.scrollIntoView({ behavior: 'smooth' });
        } else {
            console.error("Sekce neexistuje:", targetId);
        }
    });
});

// Mobilní rozbalovací menu
document.querySelector(".menu-toggle").addEventListener("click", function() {
    const nav = document.querySelector("nav");
    nav.style.display = nav.style.display === "block" ? "none" : "block";
});
function kopirujRRK() {
    let rrkHodnota = document.getElementById("rrk").value;
    for (let i = 2; i <= 12; i++) {
        document.getElementById("rrk" + i).value = rrkHodnota;
    }
}

function odeslatData() {
    let data = {
        paramtr33: document.getElementById("rrk").value,
        parametryMRK: [],
        parametryMax: []
    };

    for (let i = 1; i <= 12; i++) {
        data.parametryMRK.push(document.getElementById("mrk" + i).value);
        data.parametryMax.push(document.getElementById("max" + i).value);
    }

    fetch("/vypocet", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(result => {
        document.getElementById("vysledky").innerHTML = `
            <p>Minimální náklady: ${result.min_naklady} Kč</p>
            <p>Úspora: ${result.uspora} Kč</p>
        `;
    })
    .catch(error => console.error("Chyba:", error));
}

