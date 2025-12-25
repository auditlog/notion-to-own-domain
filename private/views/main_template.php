<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- Dynamiczny tytuł strony -->
    <title><?php echo htmlspecialchars($pageTitle) . ($mainPageTitle !== $pageTitle && !empty($requestPath) ? ' - ' . htmlspecialchars($mainPageTitle) : ''); ?></title> 
    
    <!-- Meta tagi SEO i dla robotów -->
    <meta name="description" content="<?php echo htmlspecialchars($metaDescription); ?>">
    <meta name="robots" content="noindex, nofollow"> 
    <!-- Dodatkowa dyrektywa dla Google (choć robots.txt jest ważniejszy dla AI) -->
    <meta name="googlebot" content="noindex, nofollow"> 

    <!-- Meta tagi Open Graph dla social media -->
    <meta property="og:title" content="<?php echo htmlspecialchars($ogTitle); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($metaDescription); ?>">
    <meta property="og:type" content="website">
    <?php if (!empty($currentUrl)): ?>
    <meta property="og:url" content="<?php echo htmlspecialchars($currentUrl); ?>">
    <?php endif; ?>
    <!-- Możesz dodać og:image jeśli masz stałe logo lub sposób na pobranie obrazka strony -->
    <!-- <meta property="og:image" content="URL_DO_OBRAZKA"> -->

    <link rel="stylesheet" href="/css/style.css"> 
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/prismjs@1.24.1/themes/prism.css">
    <!-- Dodaj link do KaTeX CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/katex@0.16.0/dist/katex.min.css" integrity="sha384-Xi8rHCmBmhbuyyhbI88391ZKP2dmfnOl4rT9ZfRI7mLTdk1wblIUnrIq35nqwEvC" crossorigin="anonymous">
</head>
<body>

    <?php // --- OKŁADKA PRZENIESIONA TUTAJ (nad kontenerem) --- ?>
    <?php if ($pageCoverUrl): ?>
        <div class="page-cover-fullwidth-wrapper">
            <img src="<?php echo htmlspecialchars($pageCoverUrl); ?>" alt="Okładka strony: <?php echo htmlspecialchars($pageTitle); ?>" class="page-cover-fullwidth-image">
        </div>
    <?php endif; ?>
    <?php // --- KONIEC BLOKU OKŁADKI --- ?>

    <div class="container">
        <header>
             <h1><a href="/" class="header-link"><?php echo htmlspecialchars($pageTitle); ?></a></h1>
             <?php if (!empty($requestPath) && !$pageNotFound && $currentPageId !== $notionPageId): ?>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li><a href="/"><?php echo htmlspecialchars($mainPageTitle); ?></a> / </li>
                        <li><?php echo htmlspecialchars($pageTitle); ?></li>
                    </ol>
                </nav>
             <?php endif; ?>
        </header>
        
        <main class="content">
            <?php if ($errorMessage): ?>
                <div class="error-message">
                    <p><?php echo htmlspecialchars($errorMessage); ?></p>
                    <?php if ($pageNotFound): ?>
                        <p><a href="/">Wróć do strony głównej</a></p>
                    <?php endif; ?>
                </div>
            <?php else: ?>
            <?php echo $htmlContent; ?>
            <?php endif; ?>
        </main>
        
        <footer>
            <p>&copy; <?php echo date('Y'); ?> podstawy.ai (Artur Kurasiński & Przemek Jurgiel-Żyła)</p>
        </footer>
    </div>
    
    <script src="/js/main.js"></script> 
    <script src="https://cdn.jsdelivr.net/npm/prismjs@1.24.1/prism.min.js"></script>
    <!-- Dodaj skrypty KaTeX -->
    <script defer src="https://cdn.jsdelivr.net/npm/katex@0.16.0/dist/katex.min.js" integrity="sha384-X/XCfMm41VSsqRNwNEypKSlVKGgBzu/+1G9lM2YtKkQ2A/v81rMvG0jM2o_n_D3p" crossorigin="anonymous"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/katex@0.16.0/dist/contrib/auto-render.min.js" integrity="sha384-+XBljXPPpF+B/2ucxMgMKLRePsE_rP9wF_T_LW3H3_lRjM1jYkK+F1VqB_Y6V3M4" crossorigin="anonymous"
        onload="renderMathInElement(document.body, {
            delimiters: [
                {left: '\[', right: '\]', display: true}, // dla bloków równań
                {left: '\(', right: '\)', display: false} // dla równań w linii (poprawione dla JS stringa w PHP)
            ],
            throwOnError : false
        });"></script>
</body>
</html>
