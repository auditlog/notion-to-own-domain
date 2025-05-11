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

    <!-- Dodatkowe styles dla formularza hasła (opcjonalnie) -->
    <style>
        .password-protected-content {
            border: 1px solid #ccc;
            padding: 15px;
            margin: 15px 0;
            background-color: #f9f9f9;
        }
        .password-protected-content h4 {
            margin-top: 0;
        }
        .password-protected-content label {
            margin-right: 5px;
        }
        .password-protected-content input[type="password"] {
            padding: 5px;
        }
        .password-protected-content button {
            padding: 5px 10px;
            cursor: pointer;
        }
        .notion-toggle summary { cursor: pointer; font-weight: bold; margin-bottom: 5px;}
        .notion-toggle-content { margin-left: 20px; border-left: 2px solid #eee; padding-left: 10px; }
        .notion-bookmark, .notion-embed, .notion-video, .notion-file, .notion-equation, .callout, .notion-table-of-contents-placeholder { margin: 1em 0; padding: 1em; border: 1px solid #eee; border-radius: 4px; background-color: #f9f9f9; }
        .notion-embed iframe, .notion-video video { max-width: 100%; display: block; margin: 0 auto; }
        .caption { font-size: 0.9em; color: #555; text-align: center; margin-top: 0.5em; }
        .callout-emoji { font-size: 1.2em; margin-right: 0.5em; }
        .callout-icon-external { width: 1.5em; height: 1.5em; vertical-align: middle; margin-right: 0.5em; }
        .todo-item label { display: flex; align-items: center; }
        .todo-item input[type="checkbox"] { margin-right: 8px; }
        /* Przykładowe styles dla kolorów tekstu/tła Notion (dodaj więcej wg potrzeb) */
        .notion-gray { color: gray; } .notion-gray-bg { background-color: #f1f1f1; padding: 0.1em 0.3em; border-radius: 3px;}
        .notion-brown { color: brown; } .notion-brown-bg { background-color: #f3e9e2; padding: 0.1em 0.3em; border-radius: 3px;}
        .notion-orange { color: orange; } .notion-orange-bg { background-color: #fce9d7; padding: 0.1em 0.3em; border-radius: 3px;}
        .notion-yellow { color: #c38f00; } .notion-yellow-bg { background-color: #fdf4bf; padding: 0.1em 0.3em; border-radius: 3px;} /* Ciemniejszy żółty dla tekstu */
        .notion-green { color: green; } .notion-green-bg { background-color: #e2f2e4; padding: 0.1em 0.3em; border-radius: 3px;}
        .notion-blue { color: blue; } .notion-blue-bg { background-color: #ddebf1; padding: 0.1em 0.3em; border-radius: 3px;}
        .notion-purple { color: purple; } .notion-purple-bg { background-color: #ebe4f2; padding: 0.1em 0.3em; border-radius: 3px;}
        .notion-pink { color: pink; } .notion-pink-bg { background-color: #f8e4ec; padding: 0.1em 0.3em; border-radius: 3px;}
        .notion-red { color: red; } .notion-red-bg { background-color: #f8e4e4; padding: 0.1em 0.3em; border-radius: 3px;}
        .notion-equation { text-align: center; } /* Aby wyśrodkować blokowe równania KaTeX */
    </style>

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
             <h1><a href="/" style="color: inherit; text-decoration: none;"><?php echo htmlspecialchars($pageTitle); ?></a></h1>
             <?php if (!empty($requestPath) && !$pageNotFound && $currentPageId !== $notionPageId): ?>
                <nav aria-label="breadcrumb">
                    <ol style="list-style: none; padding: 0; margin: 10px 0 0 0;">
                        <li style="display: inline;"><a href="/"><?php echo htmlspecialchars($mainPageTitle); ?></a> / </li>
                        <li style="display: inline;"><?php echo htmlspecialchars($pageTitle); ?></li>
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
