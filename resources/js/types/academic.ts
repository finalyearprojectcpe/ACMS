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
