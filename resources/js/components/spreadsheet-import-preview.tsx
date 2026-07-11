import { useState } from 'react';

import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { ScrollArea } from '@/components/ui/scroll-area';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { schoolEmailPlaceholder, useCurrentSchool } from '@/lib/school';

type SpreadsheetField = {
    key: string;
    label: string;
    aliases?: string[];
    defaultColumnIndex?: number;
    description?: string;
    emptyValueLabel?: string;
    required?: boolean;
};

type SheetData = {
    name: string;
    rows: string[][];
};

export type SpreadsheetImportBatch = {
    importKey: string;
    batchNumber: number;
    totalBatches: number;
    startRow: number;
    totalRows: number;
    batchSize: number;
    isFinalBatch: boolean;
    mappings: Record<string, string>;
};

export type SpreadsheetImportCallbacks = {
    onSuccess: () => void;
    onError: (errors: Record<string, string>) => void;
    onFinish: () => void;
};

type SpreadsheetImportPreviewProps = {
    fields: SpreadsheetField[];
    title?: string;
    submitLabel?: string;
    submitDisabled?: boolean;
    batchSize?: number;
    onSubmit?: (
        rows: Array<Record<string, string>>,
        fileName: string | null,
        batch: SpreadsheetImportBatch,
        callbacks: SpreadsheetImportCallbacks,
    ) => void;
};

const UNMAPPED_COLUMN = '__unmapped__';
const DEFAULT_BATCH_SIZE = 200;

function createImportKey(): string {
    if (typeof crypto !== 'undefined' && 'randomUUID' in crypto) {
        return crypto.randomUUID();
    }

    return `${Date.now()}-${Math.random().toString(36).slice(2)}`;
}

export function SpreadsheetImportPreview({
    fields,
    title = 'Spreadsheet Import',
    submitLabel = 'Import Rows',
    submitDisabled = false,
    batchSize = DEFAULT_BATCH_SIZE,
    onSubmit,
}: SpreadsheetImportPreviewProps) {
    const currentSchool = useCurrentSchool();
    const [fileName, setFileName] = useState<string | null>(null);
    const [sheets, setSheets] = useState<SheetData[]>([]);
    const [activeSheetName, setActiveSheetName] = useState('');
    const [mapping, setMapping] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [importKey, setImportKey] = useState('');
    const [activeBatchIndex, setActiveBatchIndex] = useState(0);
    const [completedRows, setCompletedRows] = useState(0);
    const activeSheet = sheets.find((sheet) => sheet.name === activeSheetName);
    const headers = activeSheet?.rows[0] ?? [];
    const mappedRows = mappedSpreadsheetRows(
        activeSheet?.rows.slice(1) ?? [],
        fields,
        mapping,
    );
    const normalizedBatchSize = Math.min(
        DEFAULT_BATCH_SIZE,
        Math.max(1, batchSize),
    );
    const totalRows = mappedRows.length;
    const totalBatches = Math.max(
        1,
        Math.ceil(totalRows / normalizedBatchSize),
    );
    const batchIndex = Math.min(activeBatchIndex, totalBatches - 1);
    const batchStartIndex = batchIndex * normalizedBatchSize;
    const batchStartRow = batchStartIndex + 1;
    const currentBatchRows = mappedRows.slice(
        batchStartIndex,
        batchStartIndex + normalizedBatchSize,
    );
    const currentBatchNumber = batchIndex + 1;
    const batchEndRow = batchStartRow + currentBatchRows.length - 1;
    const visibleRows = currentBatchRows;
    const importErrors = importErrorMessages(
        errors,
        fields,
        schoolEmailPlaceholder(currentSchool),
        batchStartRow,
    );

    async function parseFile(file: File) {
        const XLSX = await import('xlsx');
        const workbook = XLSX.read(await file.arrayBuffer(), {
            type: 'array',
        });
        const parsedSheets = workbook.SheetNames.map((name) => ({
            name,
            rows: XLSX.utils
                .sheet_to_json<string[]>(workbook.Sheets[name], {
                    header: 1,
                    blankrows: false,
                    defval: '',
                })
                .map((row) => row.map((cell) => String(cell).trim())),
        })).filter((sheet) => sheet.rows.length > 0);
        const nextActiveSheet = parsedSheets[0];

        setFileName(file.name);
        setSheets(parsedSheets);
        setActiveSheetName(nextActiveSheet?.name ?? '');
        setMapping(defaultMapping(nextActiveSheet?.rows[0] ?? [], fields));
        resetImportProgress(createImportKey());
    }

    function changeActiveSheet(sheetName: string) {
        const nextSheet = sheets.find((sheet) => sheet.name === sheetName);

        setActiveSheetName(sheetName);
        setMapping(defaultMapping(nextSheet?.rows[0] ?? [], fields));
        resetImportProgress(createImportKey());
    }

    function reset() {
        setFileName(null);
        setSheets([]);
        setActiveSheetName('');
        setMapping({});
        setImportKey('');
        setActiveBatchIndex(0);
        setCompletedRows(0);
        setErrors({});
        setProcessing(false);
    }

    function resetImportProgress(nextImportKey = importKey) {
        setImportKey(nextImportKey);
        setActiveBatchIndex(0);
        setCompletedRows(0);
        setErrors({});
    }

    function updateMapping(fieldKey: string, value: string) {
        setMapping((current) => ({
            ...current,
            [fieldKey]: value === UNMAPPED_COLUMN ? '' : value,
        }));
        resetImportProgress(createImportKey());
    }

    function submitCurrentBatch() {
        if (!onSubmit || currentBatchRows.length === 0) {
            return;
        }

        const nextImportKey = importKey || createImportKey();
        const batch: SpreadsheetImportBatch = {
            importKey: nextImportKey,
            batchNumber: currentBatchNumber,
            totalBatches,
            startRow: batchStartRow,
            totalRows,
            batchSize: normalizedBatchSize,
            isFinalBatch: currentBatchNumber === totalBatches,
            mappings: Object.fromEntries(
                fields.map((field) => {
                    const columnIndex = Number.parseInt(
                        mapping[field.key] ?? '',
                        10,
                    );

                    return [
                        field.key,
                        Number.isInteger(columnIndex)
                            ? (headers[columnIndex] ??
                              `Column ${columnIndex + 1}`)
                            : 'Unmapped',
                    ];
                }),
            ),
        };

        if (!importKey) {
            setImportKey(nextImportKey);
        }

        setErrors({});
        setProcessing(true);
        onSubmit(currentBatchRows, fileName, batch, {
            onSuccess: () => {
                const nextCompletedRows = Math.min(
                    totalRows,
                    batch.startRow + currentBatchRows.length - 1,
                );

                setCompletedRows(nextCompletedRows);

                if (!batch.isFinalBatch) {
                    setActiveBatchIndex(batch.batchNumber);
                }
            },
            onError: setErrors,
            onFinish: () => setProcessing(false),
        });
    }

    function submitButtonLabel(): string {
        if (processing) {
            return totalBatches > 1
                ? `Importing batch ${currentBatchNumber} of ${totalBatches}...`
                : 'Importing...';
        }

        if (totalBatches === 1) {
            return submitLabel;
        }

        if (currentBatchNumber === totalBatches) {
            return `Import final batch (${currentBatchRows.length} records)`;
        }

        return `Import batch ${currentBatchNumber} of ${totalBatches} (${currentBatchRows.length} records)`;
    }

    return (
        <Card className="border border-border bg-background shadow-sm ring-1 ring-border/70">
            <CardHeader className="flex flex-row items-center justify-between gap-3 border-b border-border">
                <CardTitle>{title}</CardTitle>
                {fileName ? (
                    <Button
                        type="button"
                        variant="link"
                        className="h-auto p-0 text-xs text-link"
                        onClick={reset}
                    >
                        Reset
                    </Button>
                ) : null}
            </CardHeader>
            <CardContent className="flex flex-col gap-4">
                <div className="grid gap-3 sm:grid-cols-[minmax(0,1fr)_14rem]">
                    <Input
                        type="file"
                        accept=".xlsx,.xls,.csv"
                        onChange={(event) => {
                            const file = event.target.files?.[0];

                            if (file) {
                                void parseFile(file);
                            }
                        }}
                    />
                    <Select
                        value={activeSheetName || undefined}
                        disabled={sheets.length === 0}
                        onValueChange={changeActiveSheet}
                    >
                        <SelectTrigger aria-label="Select spreadsheet sheet">
                            <SelectValue placeholder="Sheet" />
                        </SelectTrigger>
                        <SelectContent>
                            {sheets.map((sheet) => (
                                <SelectItem key={sheet.name} value={sheet.name}>
                                    {sheet.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                {headers.length > 0 ? (
                    <>
                        <div className="grid gap-3 sm:grid-cols-2">
                            {fields.map((field) => (
                                <label
                                    key={field.key}
                                    className="grid gap-1 text-xs font-medium text-muted-foreground"
                                >
                                    <span>
                                        {field.label}
                                        {field.required ? (
                                            <span className="text-destructive">
                                                {' '}
                                                *
                                            </span>
                                        ) : null}
                                    </span>
                                    <Select
                                        value={
                                            mapping[field.key] === '' ||
                                            mapping[field.key] === undefined
                                                ? UNMAPPED_COLUMN
                                                : mapping[field.key]
                                        }
                                        onValueChange={(value) =>
                                            updateMapping(field.key, value)
                                        }
                                    >
                                        <SelectTrigger
                                            aria-label={`Map ${field.label} column`}
                                        >
                                            <SelectValue placeholder="Unmapped" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value={UNMAPPED_COLUMN}>
                                                Unmapped
                                            </SelectItem>
                                            {headers.map((header, index) => (
                                                <SelectItem
                                                    key={`${field.key}-${header}-${index}`}
                                                    value={index.toString()}
                                                >
                                                    {header ||
                                                        `Column ${index + 1}`}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {field.description ? (
                                        <span className="text-xs leading-5 text-muted-foreground">
                                            {field.description}
                                        </span>
                                    ) : null}
                                </label>
                            ))}
                        </div>

                        {totalRows > 0 ? (
                            <div className="border border-border bg-background px-3 py-2 text-sm shadow-sm">
                                {totalBatches > 1 ? (
                                    <>
                                        <p className="font-medium">
                                            {totalRows} records found. They will
                                            be imported in {totalBatches}{' '}
                                            batches of{' '}
                                            {formatList(
                                                batchSizes(
                                                    totalRows,
                                                    normalizedBatchSize,
                                                ).map(String),
                                            )}
                                            .
                                        </p>
                                        <p className="mt-1 text-xs text-muted-foreground">
                                            {completedRows} of {totalRows}{' '}
                                            records imported. Showing batch{' '}
                                            {currentBatchNumber} of{' '}
                                            {totalBatches}: rows {batchStartRow}
                                            -{batchEndRow}.
                                        </p>
                                    </>
                                ) : (
                                    <>
                                        <p className="font-medium">
                                            {totalRows} records found.
                                        </p>
                                        <p className="mt-1 text-xs text-muted-foreground">
                                            Showing rows{' '}
                                            {`${batchStartRow}-${batchEndRow}`}.
                                        </p>
                                    </>
                                )}
                            </div>
                        ) : null}

                        <ScrollArea className="h-[28rem] border-2 border-border bg-background shadow-sm ring-1 ring-foreground/10">
                            <Table className="text-sm">
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="sticky top-0 left-0 z-30 w-12 bg-background text-center shadow-[1px_0_0_var(--border)]">
                                            Row
                                        </TableHead>
                                        {fields.map((field) => (
                                            <TableHead
                                                key={field.key}
                                                className="sticky top-0 z-20 bg-background"
                                            >
                                                {field.label}
                                            </TableHead>
                                        ))}
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {visibleRows.length > 0 ? (
                                        visibleRows.map((row, index) => (
                                            <TableRow
                                                key={index}
                                                className="odd:bg-background even:bg-muted/30"
                                            >
                                                <TableCell className="sticky left-0 z-10 w-12 bg-inherit text-center font-medium text-muted-foreground shadow-[1px_0_0_var(--border)]">
                                                    {batchStartRow + index}
                                                </TableCell>
                                                {fields.map((field) => (
                                                    <TableCell key={field.key}>
                                                        {spreadsheetCellContent(
                                                            row[field.key] ??
                                                                '',
                                                            field,
                                                        )}
                                                    </TableCell>
                                                ))}
                                            </TableRow>
                                        ))
                                    ) : (
                                        <TableRow>
                                            <TableCell
                                                colSpan={fields.length + 1}
                                                className="py-6 text-muted-foreground"
                                            >
                                                No rows found.
                                            </TableCell>
                                        </TableRow>
                                    )}
                                </TableBody>
                            </Table>
                        </ScrollArea>

                        {importErrors.length > 0 ? (
                            <Alert variant="destructive">
                                <AlertTitle>
                                    Check the spreadsheet and try again
                                </AlertTitle>
                                <AlertDescription>
                                    <p>
                                        No records were imported. Fix these
                                        issues, then import the file again.
                                    </p>
                                    <ul className="list-disc space-y-1 pl-4">
                                        {importErrors
                                            .slice(0, 6)
                                            .map((message) => (
                                                <li key={message}>{message}</li>
                                            ))}
                                    </ul>
                                    {importErrors.length > 6 ? (
                                        <p className="mt-2">
                                            Showing the first 6 issues. Fix
                                            these and submit again.
                                        </p>
                                    ) : null}
                                </AlertDescription>
                            </Alert>
                        ) : null}

                        {onSubmit ? (
                            <div className="flex justify-end">
                                <Button
                                    type="button"
                                    disabled={
                                        processing ||
                                        submitDisabled ||
                                        currentBatchRows.length === 0 ||
                                        hasMissingRequiredFields(
                                            currentBatchRows,
                                            fields,
                                        )
                                    }
                                    onClick={submitCurrentBatch}
                                >
                                    {submitButtonLabel()}
                                </Button>
                            </div>
                        ) : null}
                    </>
                ) : null}
            </CardContent>
        </Card>
    );
}

function spreadsheetCellContent(value: string, field: SpreadsheetField) {
    if (value.trim() !== '' || !field.emptyValueLabel) {
        return value;
    }

    return (
        <Badge
            variant="outline"
            className="border-border bg-muted/60 text-muted-foreground"
        >
            {field.emptyValueLabel}
        </Badge>
    );
}

function defaultMapping(
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

function mappedSpreadsheetRows(
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

        if (normalizedHeader === '') {
            return false;
        }

        return candidates.some(
            (candidate) =>
                normalizedHeader.includes(candidate) ||
                candidate.includes(normalizedHeader),
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

function hasMissingRequiredFields(
    rows: Array<Record<string, string>>,
    fields: SpreadsheetField[],
): boolean {
    return fields
        .filter((field) => field.required)
        .some((field) =>
            rows.some((row) => (row[field.key] ?? '').trim() === ''),
        );
}

function importErrorMessages(
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

    if (normalizedMessage.includes('no department in this faculty matches')) {
        return 'Department was not found in your faculty. Use the department abbreviation or full department name.';
    }

    if (normalizedMessage.includes('belongs to a student account')) {
        return 'Email belongs to a student account. Use a staff email address instead.';
    }

    if (normalizedMessage.includes('staff member is not in this faculty')) {
        return 'Staff member is not in this faculty.';
    }

    if (normalizedMessage.includes('staff member is already')) {
        return message;
    }

    if (
        normalizedMessage.includes(
            'full name is required when importing a new staff account',
        )
    ) {
        return "Full Name is required for a new staff account. Add the staff member's full name.";
    }

    return friendlyRawMessage(message, fieldLabel);
}

function fieldLabelFor(fields: SpreadsheetField[], key: string): string {
    return fields.find((field) => field.key === key)?.label ?? key;
}

function missingFieldInstruction(fieldKey: string): string {
    if (fieldKey === 'department') {
        return 'Add a department abbreviation or full department name.';
    }

    if (fieldKey === 'name') {
        return "Add the staff member's full name.";
    }

    if (fieldKey === 'email') {
        return "Add the staff member's email address.";
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

function batchSizes(totalRows: number, batchSize: number): number[] {
    const sizes: number[] = [];

    for (let remaining = totalRows; remaining > 0; remaining -= batchSize) {
        sizes.push(Math.min(batchSize, remaining));
    }

    return sizes;
}

function formatList(values: string[]): string {
    if (values.length <= 2) {
        return values.join(' and ');
    }

    return `${values.slice(0, -1).join(', ')}, and ${values.at(-1)}`;
}
