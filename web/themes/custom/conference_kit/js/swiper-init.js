
(function ($, Drupal, once) {
  'use strict';
  Drupal.behaviors.swiperParagraph = {
    attach: function (context, settings) {
      once('swiperParagraph', '.swiper', context).forEach(function (swiperEl) {
        // Find navigation buttons *inside* this swiper only
        const nextBtn = swiperEl.querySelector('.swiper-button-next');
        const prevBtn = swiperEl.querySelector('.swiper-button-prev');
        // Initialize Swiper for this element
        new Swiper(swiperEl, {
          slidesPerView: 4,
          spaceBetween: 20,
          navigation: {
            nextEl: nextBtn,
            prevEl: prevBtn,
          },
          loop: true,
          autoplay: {
            delay: 3000,
            disableOnInteraction: false,
          },
          breakpoints: {
            0: { slidesPerView: 1 },
            768: { slidesPerView: 2 },
            1024: { slidesPerView: 3 },
            1440: { slidesPerView: 4 },
            1920: { slidesPerView: 5 },

          },
        });
      });
    }
  };
})(jQuery, Drupal, once);