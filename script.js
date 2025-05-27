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
