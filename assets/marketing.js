document.addEventListener('DOMContentLoaded', () => {
    const navToggle = document.querySelector('[data-nav-toggle]');
    const siteNav = document.getElementById('site-nav');

    if (navToggle && siteNav) {
        navToggle.addEventListener('click', () => {
            const isOpen = siteNav.classList.toggle('is-open');
            navToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });

        for (const link of siteNav.querySelectorAll('a')) {
            link.addEventListener('click', () => {
                siteNav.classList.remove('is-open');
                navToggle.setAttribute('aria-expanded', 'false');
            });
        }
    }

    for (const item of document.querySelectorAll('.faq-item')) {
        const button = item.querySelector('.faq-question');
        const answer = item.querySelector('.faq-answer');

        if (!button || !answer) {
            continue;
        }

        button.addEventListener('click', () => {
            const isExpanded = button.getAttribute('aria-expanded') === 'true';

            for (const otherItem of document.querySelectorAll('.faq-item')) {
                const otherButton = otherItem.querySelector('.faq-question');
                const otherAnswer = otherItem.querySelector('.faq-answer');

                if (!otherButton || !otherAnswer) {
                    continue;
                }

                otherButton.setAttribute('aria-expanded', 'false');
                otherAnswer.style.maxHeight = '0px';
            }

            if (!isExpanded) {
                button.setAttribute('aria-expanded', 'true');
                answer.style.maxHeight = answer.scrollHeight + 'px';
            }
        });
    }

    const revealItems = document.querySelectorAll('.reveal');

    if ('IntersectionObserver' in window) {
        const revealObserver = new IntersectionObserver((entries, observer) => {
            for (const entry of entries) {
                if (!entry.isIntersecting) {
                    continue;
                }

                entry.target.classList.add('is-visible');
                observer.unobserve(entry.target);
            }
        }, {
            threshold: 0.12,
        });

        for (const item of revealItems) {
            revealObserver.observe(item);
        }
    } else {
        for (const item of revealItems) {
            item.classList.add('is-visible');
        }
    }

    const demoForm = document.querySelector('[data-demo-form]');

    if (demoForm instanceof HTMLFormElement) {
        demoForm.addEventListener('submit', (event) => {
            event.preventDefault();

            if (!demoForm.reportValidity()) {
                return;
            }

            const formData = new FormData(demoForm);
            const email = demoForm.getAttribute('data-demo-email') || 'hello@displayflow.co.uk';
            const name = String(formData.get('name') || '').trim();
            const business = String(formData.get('business') || '').trim();
            const userEmail = String(formData.get('email') || '').trim();
            const useCase = String(formData.get('use_case') || '').trim();
            const message = String(formData.get('message') || '').trim();

            const subject = encodeURIComponent('DisplayFlow demo request');
            const bodyLines = [
                'DisplayFlow demo request',
                '',
                'Name: ' + name,
                'Business: ' + business,
                'Email: ' + userEmail,
                'Use case: ' + useCase,
                '',
                'What they want to show on screen:',
                message !== '' ? message : 'No additional notes provided.',
            ];

            window.location.href = 'mailto:' + encodeURIComponent(email) + '?subject=' + subject + '&body=' + encodeURIComponent(bodyLines.join('\n'));
        });
    }
});
