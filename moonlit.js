// moonlit.js
document.addEventListener('DOMContentLoaded', function () {
  console.log('Moonlit JS loaded ✨');

  // Ví dụ: thêm shadow cho header khi cuộn xuống
  const header = document.querySelector('.site-header');
  if (header) {
    window.addEventListener('scroll', function () {
      if (window.scrollY > 10) {
        header.classList.add('is-scrolled');
      } else {
        header.classList.remove('is-scrolled');
      }
    });
  }
});
