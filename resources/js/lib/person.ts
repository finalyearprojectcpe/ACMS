export function initialsFor(name: string, fallback = 'GM'): string {
    const initials = name
        .trim()
        .split(/\s+/)
        .slice(0, 2)
        .map((part) => part[0])
        .join('')
        .toUpperCase();

    return initials || fallback;
}

export function fullNameFor(surname: string, otherNames: string): string {
    return [surname, otherNames]
        .map((part) => part.trim())
        .filter(Boolean)
        .join(' ');
}
