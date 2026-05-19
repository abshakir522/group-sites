/**
 * @file
 * Header Dynamic Padding Top.
 */
(function ($, Drupal) {
  Drupal.behaviors.headerPaddingTop = {
    attach(context, settings) {

        const header = document.querySelector('.fixed');
        const content = document.querySelector('.site-content');


        if (header && content) {
          // Wait until all assets are loaded.
          window.addEventListener('load', () => {
            const headerHeight = header.offsetHeight;
            content.style.paddingTop = `${headerHeight}px`;
          });
        }
    },
  };
})(jQuery, Drupal);





