document.addEventListener('DOMContentLoaded', function() {
    // Ищем блок с кнопками
    const buttonContainer = document.querySelector('.flex.flex-col.md\\:flex-row.md\\:items-center.justify-center.md\\:justify-end.space-y-2.md\\:space-y-0.md\\:space-x-3');
    
    if (buttonContainer) {
        // Клонируем кнопки
        const fixedButtons = buttonContainer.cloneNode(true);
        fixedButtons.classList.add('fixed-action-buttons');
        
        // Добавляем их в конец body
        document.body.appendChild(fixedButtons);
    }
});