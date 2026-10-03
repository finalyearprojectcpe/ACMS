import { CopyableValue } from '@/components/copyable-value';

export function MatchedStudentIdentity({
    name,
    registrationNumber,
}: {
    name: string;
    registrationNumber?: string;
}) {
    return (
        <dl className="grid gap-x-4 gap-y-3 border-y border-border py-4 sm:grid-cols-[7rem_minmax(0,1fr)]">
            <dt className="self-center text-xs text-muted-foreground">
                Official name
            </dt>
            <dd className="min-w-0 text-base font-semibold break-words">
                {name}
            </dd>
            {registrationNumber && (
                <>
                    <dt className="self-center text-xs text-muted-foreground">
                        Registration number
                    </dt>
                    <dd className="min-w-0">
                        <CopyableValue value={registrationNumber} />
                    </dd>
                </>
            )}
        </dl>
    );
}
