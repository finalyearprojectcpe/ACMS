export type SpreadsheetField = {
    key: string;
    label: string;
    aliases?: string[];
    defaultColumnIndex?: number;
    description?: string;
    emptyValueLabel?: string;
    required?: boolean;
};

export function createImportKey(): string {
    if (typeof crypto !== 'undefined' && 'randomUUID' in crypto) {
        return crypto.randomUUID();
    }

    return `${Date.now()}-${Math.random().toString(36).slice(2)}`;
}

export function defaultMapping(
    headers: string[],
    fields: SpreadsheetField[],
): Record<string, string> {
    return Object.fromEntries(
        fields.map((field, fieldIndex) => {
            const headerIndex = matchingHeaderIndex(headers, field);
            const fallbackIndex = field.defaultColumnIndex ?? fieldIndex;
            const index =
                headerIndex >= 0
                    ? headerIndex
                    : headers[fallbackIndex] !== undefined
                      ? fallbackIndex
                      : -1;

            return [field.key, index >= 0 ? index.toString() : ''];
        }),
    );
}

export function mappedSpreadsheetRows(
    rows: string[][],
    fields: SpreadsheetField[],
    mapping: Record<string, string>,
): Array<Record<string, string>> {
    return rows
        .map((row) =>
            Object.fromEntries(
                fields.map((field) => {
                    const columnIndex = mappedColumnIndex(mapping[field.key]);

                    return [
                        field.key,
                        columnIndex === null ? '' : (row[columnIndex] ?? ''),
                    ];
                }),
            ),
        )
        .filter((row) => Object.values(row).some((value) => value !== ''));
}

export function hasMissingRequiredFields(
    rows: Array<Record<string, string>>,
    fields: SpreadsheetField[],
): boolean {
    return fields
        .filter((field) => field.required)
        .some((field) =>
            rows.some((row) => (row[field.key] ?? '').trim() === ''),
        );
}

export function importErrorMessages(
    errors: Record<string, string>,
    fields: SpreadsheetField[],
    emailExample: string,
    rowOffset: number,
): string[] {
    const generalErrors: string[] = [];
    const rowErrors = new Map<string, { rows: number[]; message: string }>();

    Object.entries(errors).forEach(([key, message]) => {
        const rowFieldMatch = key.match(/^rows\.(\d+)\.([^.]+)$/);

        if (!rowFieldMatch) {
            generalErrors.push(importGeneralErrorMessage(key, message, fields));

            return;
        }

        const rowNumber = rowOffset + Number(rowFieldMatch[1]);
        const fieldKey = rowFieldMatch[2];
        const fieldLabel = fieldLabelFor(fields, fieldKey);
        const friendlyMessage = importRowErrorMessage(
            fieldKey,
            fieldLabel,
            message,
            emailExample,
        );
        const group = rowErrors.get(friendlyMessage) ?? {
            rows: [],
            message: friendlyMessage,
        };

        group.rows.push(rowNumber);
        rowErrors.set(friendlyMessage, group);
    });

    return [
        ...generalErrors,
        ...Array.from(rowErrors.values()).map(
            (group) => `${formatRows(group.rows)}: ${group.message}`,
        ),
    ];
}

export function batchSizes(totalRows: number, batchSize: number): number[] {
    const sizes: number[] = [];

    for (let remaining = totalRows; remaining > 0; remaining -= batchSize) {
        sizes.push(Math.min(batchSize, remaining));
    }

    return sizes;
}

function normalized(value: string): string {
    return value.toLowerCase().replace(/[^a-z0-9]+/g, '');
}

function matchingHeaderIndex(
    headers: string[],
    field: SpreadsheetField,
): number {
    const candidates = [field.label, ...(field.aliases ?? [])]
        .map(normalized)
        .filter(Boolean);
    const exactIndex = headers.findIndex((header) =>
        candidates.includes(normalized(header)),
    );

    if (exactIndex >= 0) {
        return exactIndex;
    }

    return headers.findIndex((header) => {
        const normalizedHeader = normalized(header);

        return (
            normalizedHeader !== '' &&
            candidates.some(
                (candidate) =>
                    normalizedHeader.includes(candidate) ||
                    candidate.includes(normalizedHeader),
            )
        );
    });
}

function mappedColumnIndex(value: string | undefined): number | null {
    if (value === undefined || value === '') {
        return null;
    }

    const columnIndex = Number(value);

    return Number.isInteger(columnIndex) && columnIndex >= 0
        ? columnIndex
        : null;
}

function importGeneralErrorMessage(
    key: string,
    message: string,
    fields: SpreadsheetField[],
): string {
    const normalizedMessage = message.toLowerCase();

    if (key === 'rows') {
        const maxRowsMatch = message.match(/more than (\d+) items/i);

        if (maxRowsMatch) {
            return `This batch has more than ${maxRowsMatch[1]} records. Import the batch shown on this page, then continue with the next batch.`;
        }

        if (
            normalizedMessage.includes('required') ||
            normalizedMessage.includes('at least')
        ) {
            return 'Choose a spreadsheet with at least one row to import.';
        }

        return friendlyRawMessage(message, 'spreadsheet');
    }

    if (key === 'file_name') {
        return 'The file name is too long. Rename the file and try again.';
    }

    return friendlyRawMessage(message, fieldLabelFor(fields, key));
}

function importRowErrorMessage(
    fieldKey: string,
    fieldLabel: string,
    message: string,
    emailExample: string,
): string {
    const normalizedMessage = message.toLowerCase();

    if (normalizedMessage.includes('valid email address')) {
        return `${fieldLabel} is not valid. Use an email like ${emailExample}.`;
    }

    if (normalizedMessage.includes('required')) {
        return `${fieldLabel} is missing. ${missingFieldInstruction(fieldKey)}`;
    }

    if (
        normalizedMessage.includes('must not be greater than') ||
        normalizedMessage.includes('may not be greater than')
    ) {
        return `${fieldLabel} is too long. Shorten it and try again.`;
    }

    if (normalizedMessage.includes('already exists in your workspace')) {
        return 'A student with this registration number already exists in your workspace.';
    }

    if (
        normalizedMessage.includes('already listed on row') ||
        normalizedMessage.includes('registration number')
    ) {
        return message;
    }

    return friendlyRawMessage(message, fieldLabel);
}

function fieldLabelFor(fields: SpreadsheetField[], key: string): string {
    return fields.find((field) => field.key === key)?.label ?? key;
}

function missingFieldInstruction(fieldKey: string): string {
    if (fieldKey === 'name') {
        return "Add the student's full name.";
    }

    if (fieldKey === 'email') {
        return "Add the student's email address.";
    }

    return 'Add the missing value.';
}

function friendlyRawMessage(message: string, fieldLabel: string): string {
    const cleanedMessage = message
        .replace(/^The rows\.\d+\.[a-zA-Z_]+ field /i, '')
        .replace(/^The rows field /i, '')
        .replace(/^The [^.]+ field /i, '')
        .replace(/rows\.\d+\.[a-zA-Z_]+/gi, fieldLabel.toLowerCase())
        .replace(/rows/gi, 'spreadsheet rows')
        .replace(/\.$/, '');

    return `${fieldLabel} ${cleanedMessage}.`;
}

function formatRows(rows: number[]): string {
    const uniqueRows = [...new Set(rows)].sort((left, right) => left - right);

    if (uniqueRows.length === 1) {
        return `Row ${uniqueRows[0]}`;
    }

    return `Rows ${formatList(uniqueRows.map(String))}`;
}

export function formatList(values: string[]): string {
    if (values.length <= 2) {
        return values.join(' and ');
    }

    return `${values.slice(0, -1).join(', ')}, and ${values.at(-1)}`;
}
