document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('form[data-disable-on-submit="true"]').forEach((form) => {
    form.addEventListener('submit', () => {
      const submit = form.querySelector('button[type="submit"], input[type="submit"]');

      if (!submit) return;

      submit.disabled = true;
      submit.dataset.originalText = submit.textContent;
      submit.textContent = submit.dataset.loadingText || 'Traitement...';
    });
  });
});
