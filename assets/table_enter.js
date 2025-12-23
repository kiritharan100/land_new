document.addEventListener('DOMContentLoaded', (event) => {
    function moveToNextInput(currentInput) {
        const table = currentInput.closest('table');
        const currentCell = currentInput.closest('td');
        const currentRow = currentInput.closest('tr');

        const cellIndex = Array.from(currentRow.cells).indexOf(currentCell);
        const rowIndex = Array.from(table.rows).indexOf(currentRow);

        const nextRow = table.rows[rowIndex + 1];

        if (nextRow) {
            const nextCell = nextRow.cells[cellIndex];
            const nextInput = nextCell.querySelector('input[type="number"]');
            if (nextInput) {
                nextInput.focus();
                nextInput.select();
            }
        }
    }

    function handleKeydown(event) {
        if (event.key === 'Enter') {
            event.preventDefault();
            moveToNextInput(event.target);
        }
    }

    const inputFields = document.querySelectorAll('table input[type="number"]');
    inputFields.forEach(input => {
        input.addEventListener('keydown', handleKeydown);
    });
});