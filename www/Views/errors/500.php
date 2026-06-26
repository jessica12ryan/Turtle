<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>500 - Server Error</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
    <div class="text-center">
        <h1 class="text-9xl font-bold text-gray-300">500</h1>
        <p class="text-2xl text-gray-600 mt-4">Something went wrong</p>
        <p class="text-gray-500 mt-2">An unexpected error occurred. Please try again later.</p>
        <div class="mt-6 flex items-center justify-center space-x-4">
            <button onclick="history.back()" class="bg-gray-200 text-gray-700 px-6 py-3 rounded-lg hover:bg-gray-300">Go Back</button>
            <a href="/home" class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700">Go to Home</a>
        </div>
    </div>
</body>
</html>
