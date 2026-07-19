/* ==========================================
   CORE PUBLIC JS
========================================== */

console.log('PUBLIC JS OK');

document.addEventListener('DOMContentLoaded', function () {

    console.log('DOM READY');

    initSmoothScroll();
    initWhatsAppTracking();
    initLeadForm();
    initSidebarMenu();

});

/* ==========================================
   Smooth Scroll
========================================== */

function initSmoothScroll() {

    const links = document.querySelectorAll('a[href^="#"]');

    if (!links.length) return;

    links.forEach(link => {
        link.addEventListener('click', function (e) {

            const targetId = this.getAttribute('href');

            if (!targetId || targetId === '#') return;

            const target = document.querySelector(targetId);

            if (!target) return;

            e.preventDefault();

            window.scrollTo({
                top: target.offsetTop - 20,
                behavior: 'smooth'
            });
        });
    });
}

/* ==========================================
   WhatsApp Tracking
========================================== */

function initWhatsAppTracking() {

    const whatsappLinks = document.querySelectorAll('.cta_whatsapp a');

    if (!whatsappLinks.length) return;

    whatsappLinks.forEach(link => {
        link.addEventListener('click', function () {
            console.log('WhatsApp CTA clicked');
        });
    });
}

/* ==========================================
   Lead Form
========================================== */

function initLeadForm() {

    const form = document.querySelector('.form_lead_simple form');

    if (!form) return;

    form.addEventListener('submit', function () {

        const button = form.querySelector('button');

        if (button) {
            button.disabled = true;
            button.innerText = 'Enviando...';
        }

    });
}

/* ==========================================
   SIDEBAR MENU (ROBUSTO)
========================================== */

function initSidebarMenu() {

    document.addEventListener('click', function(e){

        /* CATEGORIA */
        const cat = e.target.closest('.menu-cat-title');

        if(cat){

            const wrapper = cat.closest('.menu-category');
            const container = cat.nextElementSibling;

            if(!container) return;

            const isOpen = container.style.display === 'block';

            container.style.display = isOpen ? 'none' : 'block';

            wrapper.classList.toggle('open', !isOpen);

            return;
        }

        /* SUB */
        const sub = e.target.closest('.menu-sub-title');

        if(sub){

            const wrapper = sub.closest('.menu-sub');
            const list = wrapper.querySelector('.menu-post-list');

            if(!list) return;

            const isOpen = list.style.display === 'block';

            list.style.display = isOpen ? 'none' : 'block';

            wrapper.classList.toggle('open', !isOpen);

            return;
        }

    });

}