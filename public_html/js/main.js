// JavaScript for better interactivity
document.addEventListener('DOMContentLoaded', function() {
    // Create table of contents
    const content = document.querySelector('.content');
    const headings = content.querySelectorAll('h1, h2, h3');
    const tocPlaceholder = content.querySelector('.notion-table-of-contents-placeholder');

    // Only generate TOC if there's a placeholder from Notion's table_of_contents block
    // or if there are many headings and no placeholder (fallback behavior)
    const shouldGenerateToc = tocPlaceholder || headings.length > 5;

    if (shouldGenerateToc && headings.length > 0) {
        const toc = document.createElement('div');
        toc.className = 'table-of-contents';
        toc.innerHTML = '<ul></ul>';

        const tocList = toc.querySelector('ul');

        headings.forEach((heading, index) => {
            // Add id to heading if it doesn't have one
            if (!heading.id) {
                heading.id = 'heading-' + index;
            }

            const item = document.createElement('li');
            const link = document.createElement('a');
            link.href = '#' + heading.id;
            link.textContent = heading.textContent;
            item.appendChild(link);

            // Add CSS class for indentation based on heading level
            if (heading.tagName === 'H2') {
                item.className = 'toc-h2';
            } else if (heading.tagName === 'H3') {
                item.className = 'toc-h3';
            }

            tocList.appendChild(item);
        });

        // Insert TOC in place of placeholder, or at beginning if no placeholder
        if (tocPlaceholder) {
            tocPlaceholder.replaceWith(toc);
        } else {
            content.insertBefore(toc, content.firstChild);
        }
    } else if (tocPlaceholder) {
        // Remove empty placeholder if not enough headings
        tocPlaceholder.remove();
    }

    // Image lightbox functionality
    const images = document.querySelectorAll('.content img');
    images.forEach(img => {
        img.addEventListener('click', function() {
            const overlay = document.createElement('div');
            overlay.className = 'image-overlay';
            overlay.innerHTML = `<div class="image-overlay-content">
                <img src="${this.src}" alt="${this.alt}">
                <button class="close-overlay">Zamknij</button>
            </div>`;

            document.body.appendChild(overlay);

            overlay.querySelector('.close-overlay').addEventListener('click', function() {
                document.body.removeChild(overlay);
            });

            overlay.addEventListener('click', function(e) {
                if (e.target === overlay) {
                    document.body.removeChild(overlay);
                }
            });
        });
    });
});
