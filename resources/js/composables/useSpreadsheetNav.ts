import { nextTick, type Ref } from 'vue';

export type SpreadsheetNavOptions = {
    rowCount: Ref<number>;
    colCount: Ref<number>;
    /** Resolve the input element for a cell coordinate. */
    getCellInput: (row: number, col: number) => HTMLInputElement | null;
    /** Called when Delete/Backspace should clear the focused cell. */
    onClear?: (row: number, col: number) => void;
};

/**
 * Excel-style keyboard navigation for a dense editable grid.
 * Arrow keys move focus; Tab / Shift+Tab move horizontally;
 * Enter moves down; Delete/Backspace clear via onClear.
 */
export function useSpreadsheetNav(options: SpreadsheetNavOptions) {
    function focusCell(row: number, col: number): void {
        const maxRow = options.rowCount.value - 1;
        const maxCol = options.colCount.value - 1;
        if (maxRow < 0 || maxCol < 0) {
            return;
        }

        const r = Math.max(0, Math.min(row, maxRow));
        const c = Math.max(0, Math.min(col, maxCol));

        void nextTick(() => {
            const el = options.getCellInput(r, c);
            if (!el) {
                return;
            }
            el.focus();
            el.select();
        });
    }

    function handleKeydown(
        event: KeyboardEvent,
        row: number,
        col: number,
    ): void {
        const key = event.key;
        const maxRow = options.rowCount.value - 1;
        const maxCol = options.colCount.value - 1;

        if (key === 'ArrowRight') {
            event.preventDefault();
            focusCell(row, Math.min(col + 1, maxCol));
            return;
        }
        if (key === 'ArrowLeft') {
            event.preventDefault();
            focusCell(row, Math.max(col - 1, 0));
            return;
        }
        if (key === 'ArrowDown') {
            event.preventDefault();
            focusCell(Math.min(row + 1, maxRow), col);
            return;
        }
        if (key === 'ArrowUp') {
            event.preventDefault();
            focusCell(Math.max(row - 1, 0), col);
            return;
        }
        if (key === 'Tab') {
            event.preventDefault();
            if (event.shiftKey) {
                if (col > 0) {
                    focusCell(row, col - 1);
                } else if (row > 0) {
                    focusCell(row - 1, maxCol);
                }
            } else if (col < maxCol) {
                focusCell(row, col + 1);
            } else if (row < maxRow) {
                focusCell(row + 1, 0);
            }
            return;
        }
        if (key === 'Enter') {
            event.preventDefault();
            focusCell(Math.min(row + 1, maxRow), col);
            return;
        }
        if (key === 'Escape') {
            event.preventDefault();
            (event.target as HTMLInputElement | null)?.blur();
            return;
        }
        if (
            (key === 'Delete' || key === 'Backspace') &&
            options.onClear &&
            !(event.target as HTMLInputElement).value
        ) {
            // Already empty — still invoke clear for consistency
            event.preventDefault();
            options.onClear(row, col);
            return;
        }
        if (
            (key === 'Delete' || key === 'Backspace') &&
            options.onClear &&
            (event.target as HTMLInputElement).selectionStart === 0 &&
            (event.target as HTMLInputElement).selectionEnd ===
                (event.target as HTMLInputElement).value.length
        ) {
            // Entire value selected — clear cell Excel-style
            event.preventDefault();
            options.onClear(row, col);
        }
    }

    return { focusCell, handleKeydown };
}
