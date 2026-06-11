document.addEventListener('click', function (event) {
    var confirmMessage = event.target.getAttribute('data-confirm');
    if (confirmMessage && !window.confirm(confirmMessage)) {
        event.preventDefault();
    }
});

var addLine = document.getElementById('add-line');
var cartLines = document.getElementById('cart-lines');

if (addLine && cartLines) {
    addLine.addEventListener('click', function () {
        var firstLine = cartLines.querySelector('.cart-line');
        var clone = firstLine.cloneNode(true);
        clone.querySelector('select').value = '';
        clone.querySelector('input').value = 1;
        cartLines.appendChild(clone);
    });

    cartLines.addEventListener('click', function (event) {
        if (!event.target.classList.contains('remove-line')) {
            return;
        }

        if (cartLines.querySelectorAll('.cart-line').length > 1) {
            event.target.closest('.cart-line').remove();
        }
    });
}
