// Kod JavaScript dla lepszej interaktywności
document.addEventListener('DOMContentLoaded', function() {
    // Tworzenie spisu treści
    const content = document.querySelector('.content');
    const headings = content.querySelectorAll('h1, h2, h3');
    
    if (headings.length > 3) {
        const toc = document.createElement('div');
        toc.className = 'table-of-contents';
        toc.innerHTML = '<h2>Spis treści</h2><ul></ul>';
        
        const tocList = toc.querySelector('ul');
        
        headings.forEach((heading, index) => {
            // Dodaj id do nagłówka, jeśli nie ma
            if (!heading.id) {
                heading.id = 'heading-' + index;
            }
            
            const item = document.createElement('li');
            const link = document.createElement('a');
            link.href = '#' + heading.id;
            link.textContent = heading.textContent;
            item.appendChild(link);
            
            // Indentacja dla różnych poziomów nagłówków
            if (heading.tagName === 'H2') {
                item.style.marginLeft = '20px';
            } else if (heading.tagName === 'H3') {
                item.style.marginLeft = '40px';
            }
            
            tocList.appendChild(item);
        });
        
        // Wstaw spis treści na początku zawartości
        content.insertBefore(toc, content.firstChild);
    }
    
    // Obsługa obrazów - dodanie efektu lightbox
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
        
        // Dodaj wskaźnik, że można kliknąć
        img.style.cursor = 'pointer';
    });
    
    console.log('Strona załadowana poprawnie');
});

// Dodaj style dla lightboxa
document.head.insertAdjacentHTML('beforeend', `
<style>
.table-of-contents {
    background-color: #f9f9f9;
    padding: 15px;
    margin-bottom: 30px;
    border: 1px solid #eee;
    border-radius: 5px;
}

.table-of-contents h2 {
    margin-top: 0;
}

.image-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.8);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 1000;
}

.image-overlay-content {
    position: relative;
    max-width: 90%;
    max-height: 90%;
}

.image-overlay img {
    max-width: 100%;
    max-height: 90vh;
    margin: 0;
}

.close-overlay {
    position: absolute;
    top: -40px;
    right: 0;
    background-color: white;
    border: none;
    padding: 5px 10px;
    cursor: pointer;
}
</style>
`);