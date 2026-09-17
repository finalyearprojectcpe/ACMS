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
import {
    batchSizes,
    createImportKey,
    defaultMapping,
    formatList,
    hasMissingRequiredFields,
    importErrorMessages,
    mappedSpreadsheetRows,
} from '@/lib/spreadsheet-import';
import type { SpreadsheetField } from '@/lib/spreadsheet-import';

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

export function SpreadsheetImportPreview({
    fields,
    title = 'Spreadsheet Import',
    submitLabel = 'Import Rows',
    submitDisabled = false,
    batchSize = DEFAULT_BATCH_SIZE,
    onSubmit,
}: SpreadsheetImportPreviewProps) {
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
        'name.com',
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
