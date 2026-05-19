/**
 * @file
 * Provides UI/UX progressive enhancements on conference_kit's theme settings by
 * creating an HTMLColorInput element and synchronizing its input with a text
 * input to provide an accessible and user-friendly interface. Additionally,
 * provides a select element with pre-defined color values for easy color
 * switching.
 */

((Drupal, settings, once) => {
  const colorSchemeCategories = settings.conference_kit.colorSchemes || {};

  /**
   * Announces the text value of the field's label.
   *
   * @param {HTMLElement} changedInput
   *  The form element that was changed.
   */
  function announceFieldChange(changedInput) {
    const fieldTitle =
      changedInput.parentElement.querySelector('label').innerText;
    const fieldValue = changedInput.value;
    const announcement = Drupal.t('@fieldName has changed to @fieldValue', {
      '@fieldName': fieldTitle,
      '@fieldValue': fieldValue,
    });
    Drupal.announce(announcement);
  }

  /**
   * Formats hexcode to full 6-character string for HTMLColorInput.
   *
   * @param {string} hex The hex code input.
   * @returns {string} The same hex code, formatted.
   */
  function formatHex(hex) {
    // Temporarily remove hash
    if (hex.startsWith('#')) {
      hex = hex.substring(1);
    }

    // Convert three-value to six-value syntax.
    if (hex.length === 3) {
      hex = hex
        .split('')
        .flatMap((character) => [character, character])
        .join('');
    }

    hex = `#${hex}`;

    return hex;
  }

  /**
   * `input` event callback to keep text & color inputs in sync.
   *
   * @param {HTMLElement} changedInput input element changed by user
   * @param {HTMLElement} inputToSync input element to synchronize
   */
  function synchronizeInputs(changedInput, inputToSync) {
    inputToSync.value = formatHex(changedInput.value);

    changedInput.setAttribute(
      'data-conference_kit-custom-color',
      formatHex(changedInput.value),
    );
    inputToSync.setAttribute(
      'data-conference_kit-custom-color',
      formatHex(changedInput.value),
    );

    const colorSchemeSelect = changedInput.closest('details')?.querySelector('[data-drupal-selector="edit-conference_kit-color-scheme"]');

    if (colorSchemeSelect && colorSchemeSelect.value !== '') {
      colorSchemeSelect.value = '';
      announceFieldChange(colorSchemeSelect);
    }
  }

  /**
   * Set individual colors when a pre-defined color scheme is selected.
   *
   * @param {Event.target} target input element for which the value has changed.
   */

  function setColorScheme({ target }) {
    if (!target.value) return;

    // Find the category (siteColors, statusColors, etc.)
    const category = target.getAttribute('data-category');
    if (!category || !colorSchemeCategories[category]) return;

    const selectedColorScheme = colorSchemeCategories[category][target.value]?.colors;
    if (!selectedColorScheme) return;

    Object.entries(selectedColorScheme).forEach(([key, color]) => {
      document.querySelectorAll(`input[name="${key}"], input[name="${key}_visual"]`)
        .forEach((input) => {
          if (input.value !== color) {
            input.value = color;
            if (input.type === 'text') {
              announceFieldChange(input);
            }
          }
        });
    });
  }

  /**
   * Displays and initializes the color scheme selector.
   *
   * @param {HTMLSelectElement} selectElement div[data-drupal-selector="edit-conference_kit-color-scheme"]
   */

  function initColorSchemeSelect(selectElement, category) {
    selectElement.closest('[style*="display:none;"]').style.display = '';
    selectElement.setAttribute('data-category', category);
    selectElement.addEventListener('change', setColorScheme);

    Object.entries(colorSchemeCategories[category]).forEach(([key, values]) => {
      const { label, colors } = values;
      let allColorsMatch = true;

      Object.entries(colors).forEach(([colorName, colorHex]) => {
        const field = document.querySelector(`input[name="${colorName}"]`);
        if (field.value !== colorHex) {
          allColorsMatch = false;
        }
      });

      if (allColorsMatch) {
        selectElement.value = key;
      }
    });
  }


  /**
   * Initializes conference_kit theme-settings color picker.
   *   creates a color-type input and inserts it after the original text field.
   *   modifies aria values to make label apply to both inputs.
   *   adds event listeners to keep text & color inputs in sync.
   *
   * @param {HTMLElement} textInput The textfield input from the Drupal form API
   */
  function initColorPicker(textInput) {
    // Create input element.
    const colorInput = document.createElement('input');

    // Set new input's attributes.
    colorInput.type = 'color';
    colorInput.classList.add(
      'form-color',
      'form-element',
      'form-element--type-color',
      'form-element--api-color',
    );
    colorInput.value = formatHex(textInput.value);
    colorInput.setAttribute('name', `${textInput.name}_visual`);
    colorInput.setAttribute(
      'data-conference_kit-custom-color',
      textInput.getAttribute('data-conference_kit-custom-color'),
    );

    // Insert new input into DOM.
    textInput.after(colorInput);

    // Make field label apply to textInput and colorInput.
    const fieldID = textInput.id;
    const label = document.querySelector(`label[for="${fieldID}"]`);
    label.removeAttribute('for');
    label.setAttribute('id', `${fieldID}-label`);

    textInput.setAttribute('aria-labelledby', `${fieldID}-label`);
    colorInput.setAttribute('aria-labelledby', `${fieldID}-label`);

    // Add `input` event listener to keep inputs synchronized.
    textInput.addEventListener('input', () => {
      synchronizeInputs(textInput, colorInput);
    });

    colorInput.addEventListener('input', () => {
      synchronizeInputs(colorInput, textInput);
    });
  }

  /**
   * conference_kit Color Picker behavior.
   *
   * @type {Drupal~behavior}
   * @prop {Drupal~behaviorAttach} attach
   *   Initializes color picker fields.
   */
  Drupal.behaviors.conference_kitColorPicker = {
    attach: () => {
      const colorSchemeSelects = once(
        'conference_kit-color-picker',
        '[data-drupal-selector^="edit-conference_kit-color-scheme"]',
      );
      colorSchemeSelects.forEach((selectElement) => {
        const category = selectElement.getAttribute('data-category');
        if (!category || !colorSchemeCategories[category]) return;

        initColorSchemeSelect(selectElement, category);
      });

      const colorTextInputs = once(
        'conference_kit-color-picker',
        '[data-drupal-selector="conference_kit-color-picker"] input[type="text"]',
      );

      colorTextInputs.forEach((textInput) => {
        initColorPicker(textInput);
      });
    },
  };
})(Drupal, drupalSettings, once);
