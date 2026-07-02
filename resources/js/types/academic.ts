export type SchoolContext = {
    id: number;
    name: string;
    abbreviation: string;
    slug: string;
    domainKey: string;
};

export type DepartmentOption = {
    id: number;
    name: string;
    abbreviation: string;
};

export type DepartmentGroup = {
    id: number;
    name: string;
    abbreviation: string;
    departments: DepartmentOption[];
};
