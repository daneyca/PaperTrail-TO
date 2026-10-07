export default {
    content: [
        './resources/views/**/*.blade.php',
        './resources/js/**/*.js',
    ],
    corePlugins: {
        preflight: false,
    },
    theme: {
        extend: {
            colors: {
                papertrail: {
                    navy: '#071d3a',
                    blue: '#2563eb',
                    gold: '#f4b72f',
                    soft: '#f6f9fd',
                },
            },
            boxShadow: {
                papertrail: '0 16px 40px rgba(15, 30, 52, 0.08)',
            },
        },
    },
    plugins: [],
};
