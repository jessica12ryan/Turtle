/** @type {import('tailwindcss').Config} */
module.exports = {
    darkMode: 'class',
    content: [
        '../www/Views/**/*.php',
        '../www/index.php',
        '../www/Core/**/*.php',
        '../www/functions.php',
    ],
    safelist: [
        // Class names assembled at runtime rather than written literally.
        'hidden',
        'flex',
        'w-0',
        'bg-blue-600',
        'bg-yellow-500',
        'bg-blue-50',
        'text-blue-700',
        'text-gray-700',
        'bg-gray-50',
        'bg-gray-100',
        'text-gray-800',
        'dark:bg-slate-700',
        'dark:text-slate-100',
        'bg-red-500',
    ],
    theme: { extend: {} },
    plugins: [],
};
