/** @type {import('tailwindcss').Config} */
export default {
  content: [
    './index.html',
    './src/**/*.{js,ts,jsx,tsx}',
    // PHP visitor-facing app (used when compiling a real Tailwind build)
    './app/views/**/*.php',
    './public/assets/js/**/*.js',
  ],
  theme: {
    extend: {
      fontFamily: {
        sans: ['Inter', 'system-ui', 'sans-serif'],
        display: ['Sora', 'Inter', 'system-ui', 'sans-serif'],
      },
      animation: {
        'fade-in-up': 'fadeInUp 0.6s ease-out both',
        'slide-down': 'slideDown 0.35s ease-out both',
        'success-pop': 'successPop 0.5s cubic-bezier(0.34, 1.56, 0.64, 1) both',
      },
      keyframes: {
        fadeInUp: {
          from: { opacity: '0', transform: 'translateY(24px)' },
          to:   { opacity: '1', transform: 'translateY(0)' },
        },
        slideDown: {
          from: { opacity: '0', transform: 'translate(-50%, -16px)' },
          to:   { opacity: '1', transform: 'translate(-50%, 0)' },
        },
        successPop: {
          '0%':  { opacity: '0', transform: 'scale(0.4)' },
          '60%': { opacity: '1', transform: 'scale(1.15)' },
          '100%':{ transform: 'scale(1)' },
        },
      },
    },
  },
  plugins: [],
};
