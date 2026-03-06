/** @type {import('tailwindcss').Config} */
module.exports = {
  darkMode: 'selector',
  content: [
    './src/**/*.{html,js,twig,tsx,ts}',
    './vendor/wai-blue/adios/**/*.{tsx,twig}',
    './vendor/wai-blue/adios/node_modules/primereact/**/*.{js,ts,jsx,tsx}',
  ],
};
