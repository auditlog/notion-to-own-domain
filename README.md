# Notion Page Viewer PHP

This is a simple PHP project designed to fetch content from a specific Notion page (and its direct child pages) and display it as a website. It utilizes the official Notion API and includes features like server-side caching, basic block rendering (paragraphs, headings, lists, code blocks, images, tables, etc.), and URL routing based on child page titles.

## Features

*   **Notion Content Display**: Renders content from a specified Notion page.
*   **Subpage Routing**: Automatically creates routes for direct child pages of the main Notion page (e.g., `yourdomain.com/subpage-title`).
*   **Block Support**: Handles common Notion blocks including:
    *   Headings (H1, H2, H3)
    *   Paragraphs
    *   Bulleted and Numbered Lists
    *   To-do Lists
    *   Images (Internal and External)
    *   Code Blocks (with basic syntax highlighting via Prism.js)
    *   Quotes
    *   Callouts
    *   Dividers
    *   Tables (including headers)
    *   Child Page links (rendered as links on the parent page)
    *   Page Mentions (rendered as internal links within text and tables)
*   **Rich Text Formatting**: Supports bold, italics, strikethrough, underline, code annotations, and external links.
*   **Server-Side Caching**: Caches Notion API responses (page content, titles, subpage lists, table rows) to reduce API calls and improve performance. Cache duration is configurable.
*   **Dynamic Page Titles**: Uses the actual Notion page title for the HTML `<title>` tag and the main `<h1>` header.
*   **Simple Setup**: Requires minimal configuration.

## Project Structure 