import daisyui from 'daisyui'

/* Condition for creating the daisyUI CSS plugin */
const enableDaisy = process.env.DAISY === 'true'

module.exports = {
  content: [
     "./components/**/*.twig",
      "./templates/**/*.html.twig",
      "./templates/*.html.twig",
      "./components/**/*.stories.json",
      "./components/**/*.stories.yml",
      "./assets/scaffold/recipes/tailpine_content/content/node/*.yml"
    ],
    safelist: [
      ".carousel"
    ],
    theme: {
      extend: {
        screens: {
          mobile: "400px",
          tablet: "768px",
          "lg-tablet": "1140px",
          desktop: "1440px",
        },
        colors: {
          "primary-1": "#009CBB",
          "primary-2": "#006596",
          "primary-3": "#0071B3",
          "primary-4": "#0C1D24",
          "primary-5": "#F37539",
          "gray-1": "#808080",
          "gray-2": "#E6E6E6",
          "gray-3": "#FBFBFB",
          "gray-4": "#F1F1F1",
        },
        fontSize: {
          // Desktop (1440px)
          "h1-desktop": ["40px", { lineHeight: "auto", letterSpacing: "1px" }],
          "h2-desktop": ["36px", { lineHeight: "40px", letterSpacing: "1px" }],
          "h3-desktop": ["26px", { lineHeight: "36px", letterSpacing: "0px" }],
          "h4-desktop": ["24px", { lineHeight: "30px", letterSpacing: "0px" }],

          // Large Tablet (1140px)
          "h1-lg": ["36px", { lineHeight: "auto", letterSpacing: "1px" }],
          "h2-lg": ["32px", { lineHeight: "36px", letterSpacing: "1px" }],
          "h3-lg": ["24px", { lineHeight: "32px", letterSpacing: "0px" }],
          "h4-lg": ["22px", { lineHeight: "30px", letterSpacing: "0px" }],

          // Tablet (768px)
          "h1-tablet": ["32px", { lineHeight: "auto", letterSpacing: "1px" }],
          "h2-tablet": ["30px", { lineHeight: "35px", letterSpacing: "1px" }],
          "h3-tablet": ["24px", { lineHeight: "32px", letterSpacing: "0px" }],
          "h4-tablet": ["22px", { lineHeight: "30px", letterSpacing: "0px" }],

          // Mobile (400px)
          "h1-mobile": ["28px", { lineHeight: "auto", letterSpacing: "1px" }],
          "h2-mobile": ["26px", { lineHeight: "32px", letterSpacing: "1px" }],
          "h3-mobile": ["22px", { lineHeight: "30px", letterSpacing: "0px" }],
          "h4-mobile": ["20px", { lineHeight: "30px", letterSpacing: "0px" }],

          // Body Text
          "body-lg": ["18px", { lineHeight: "30px", letterSpacing: "0px" }],
          "body-md": ["16px", { lineHeight: "28px", letterSpacing: "0px" }],
          "body-sm": ["14px", { lineHeight: "26px", letterSpacing: "0px" }],
          "body-tag": ["12px", { lineHeight: "24px", letterSpacing: "0px" }],
        },
        fontFamily: {
          lato: ["Lato", "sans-serif"],
        },
      },
    },
      plugins: [
        ...(enableDaisy ? [daisyui] : [])
      ],
    }
