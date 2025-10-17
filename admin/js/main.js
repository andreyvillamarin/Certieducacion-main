document.addEventListener("DOMContentLoaded", function() {
    const menuToggle = document.getElementById("menu-toggle");
    if(menuToggle) {
        menuToggle.addEventListener("click", function(e) {
            e.preventDefault();
            const wrapper = document.getElementById("wrapper");
            if (wrapper) {
                wrapper.classList.toggle("toggled");
            }
        });
    }
});