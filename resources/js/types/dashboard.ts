export type DashboardRole = 'student' | 'staff' | 'hod' | 'faculty_head';

export type DashboardDepartment = {
    name: string;
    abbreviation: string;
};

export type DashboardFaculty = {
    name: string;
    abbreviation: string;
};

export type DashboardLevel = {
    id: number;
    title: string;
    slug: string;
};

export type DashboardStudent = {
    registrationNumber: string;
    currentLevel: DashboardLevel | null;
};

export type DashboardStaffHome = {
    assignedLevels: DashboardLevel[];
    totalStudents: number;
    maximumCourseUnits: number;
    studentsWithCarryovers: number;
    pendingApprovalsCount: number;
    pendingApprovals: DashboardStaffHomePendingApproval[];
    pendingDuesByStudent: DashboardStaffHomeStudentDueSummary[];
    recentPayments: DashboardStaffHomePayment[];
    carryoverSummaries: DashboardStaffHomeCarryoverSummary[];
};

export type DashboardStaffHomePendingApproval = {
    studentName: string;
    registrationNumber: string;
    requestType: string;
    submittedAt: string;
};

export type DashboardStaffHomeStudentDueSummary = {
    studentName: string;
    registrationNumber: string;
    dues: string[];
    outstandingAmount: string;
};

export type DashboardStaffHomePayment = {
    studentName: string;
    dueName: string;
    amount: string;
    paidAt: string;
};

export type DashboardStaffHomeCarryoverSummary = {
    code: string;
    title: string;
    units: number;
    students: string[];
};

export type DashboardStaffStudents = {
    assignedLevels: DashboardLevel[];
    totalStudents: number;
    students: DashboardStaffStudent[];
};

export type DashboardStaffStudent = {
    id: number;
    fullName: string;
    email: string;
    registrationNumber: string;
    avatar: string | null;
    currentLevel: DashboardLevel | null;
    pendingCarryovers: string[];
    levelGpas: DashboardStaffStudentLevelGpa[];
    cgpa: string;
    profileUrl: string;
};

export type DashboardStaffStudentLevelGpa = {
    level: string;
    gpa: string;
};

export type DashboardStaffCourseSchedules = {
    level: DashboardLevel;
    semesters: DashboardStaffCourseScheduleSemester[];
};

export type DashboardStaffCourseScheduleSemester = {
    id: string;
    name: string;
    slug: string;
    totalUnits: number;
    mandatoryCount: number;
    optionalCount: number;
    offerings: DashboardStaffCourseOffering[];
};

export type DashboardStaffCourseOffering = {
    id: number;
    courseId: number;
    code: string;
    title: string;
    units: number;
    owner: string;
    isMandatory: boolean;
    requirement: string;
    prerequisites: DashboardStaffCoursePrerequisite[];
    timetableSlotsCount: number;
    canEdit: boolean;
};

export type DashboardStaffCoursePrerequisite = {
    id: number;
    code: string;
    title: string;
};

export type DashboardStaffCourseOfferingForm = {
    mode: 'create' | 'edit';
    level: DashboardLevel;
    semester: DashboardStaffCourseSemester;
    offering: {
        id: number;
        code: string;
        title: string;
        creditUnits: number;
        isMandatory: boolean;
        prerequisiteCourseIds: number[];
    } | null;
    prerequisiteOptions: DashboardStaffCoursePrerequisiteOption[];
};

export type DashboardStaffCoursePrerequisiteOption = {
    id: number;
    code: string;
    title: string;
    units: number;
    level: string;
    semester: string;
};

export type DashboardStaffCourseTimetable = {
    level: DashboardLevel;
    semester: DashboardStaffCourseSemester;
    offering: {
        id: number;
        code: string;
        title: string;
        units: number;
        requirement: string;
        prerequisites: DashboardStaffCoursePrerequisite[];
    };
    weekdays: string[];
    slots: DashboardStaffCourseTimetableSlot[];
};

export type DashboardStaffCourseSemester = {
    id: number;
    name: string;
    slug: string;
};

export type DashboardStaffCourseTimetableSlot = {
    id: number;
    dayOfWeek: string;
    startsAt: string;
    endsAt: string;
    venue: string;
};

export type DashboardStudentProfile = {
    name: {
        full: string;
        surname: string;
        otherNames: string;
    };
    email: string;
    avatar: string | null;
    registrationNumber: string;
    cgpa: string;
    faculty: DashboardFaculty | null;
    department: DashboardDepartment | null;
    currentLevel: DashboardLevel | null;
    courseAdvisor: string;
};

export type DashboardStudentHome = {
    cgpa: string;
    currentSemester: {
        name: string;
        level: string;
        session: string;
    };
    totalCourseUnits: number;
    timetable: DashboardStudentHomeTimetableItem[];
    pendingDues: DashboardStudentHomeDue[];
    resits: DashboardStudentHomeResit[];
};

export type DashboardStudentHomeTimetableItem = {
    code: string;
    title: string;
    units: number;
    day: string;
    time: string;
    venue: string;
};

export type DashboardStudentHomeDue = {
    title: string;
    context: string;
    amount: string;
    dueDate: string;
};

export type DashboardStudentHomeResit = {
    code: string;
    title: string;
    units: number;
    originalSemester: string;
    resitWindow: string;
    status: string;
};

export type DashboardStudentResults = {
    level: DashboardLevel;
    averageGpa: string;
    totalUnits: number;
    failedCourses: number;
    totalCourses: number;
    semesters: DashboardStudentResultSemester[];
};

export type DashboardStudentResultSemester = {
    name: string;
    slug: string;
    gpa: string;
    totalUnits: number;
    courses: DashboardStudentResultCourse[];
};

export type DashboardStudentResultCourse = {
    code: string;
    title: string;
    units: number;
    score: number;
    grade: string;
};

export type DashboardStudentDues = {
    pendingAmount: string;
    paidAmount: string;
    totalDues: number;
    pending: DashboardStudentDue[];
    paid: DashboardStudentPaidDue[];
};

export type DashboardStudentDue = {
    name: string;
    description: string;
    amount: string;
    paymentUrl: string;
};

export type DashboardStudentPaidDue = {
    name: string;
    description: string;
    amount: string;
    paidAt: string;
    receiptUrl: string;
};

export type DashboardStudentCourseSchedules = {
    currentSemester: {
        name: string;
        session: string;
    };
    maximumCourseUnits: number;
    advisorName: string;
    reviewStatus: string;
    selectedUnits: number;
    semesters: DashboardStudentCourseScheduleSemester[];
    validCourses: DashboardStudentCourseOption[];
};

export type DashboardStudentCourseScheduleSemester = {
    id: string;
    name: string;
    level: string;
    session: string;
    isEditable: boolean;
    totalUnits: number;
    courses: DashboardStudentCourseScheduleCourse[];
};

export type DashboardStudentCourseScheduleCourse = {
    code: string;
    title: string;
    units: number;
    type: string;
    status: string;
};

export type DashboardStudentCourseOption = {
    code: string;
    title: string;
    units: number;
    type: string;
};

export type DashboardContext = {
    role: DashboardRole;
    roleLabel: string;
    section: string;
    sectionTitle: string;
    levelSlug: string | null;
    level: string | null;
    levels: DashboardLevel[];
    courseScheduleLevels: DashboardLevel[];
    department: DashboardDepartment | null;
    faculty: DashboardFaculty | null;
    staffHome: DashboardStaffHome | null;
    staffStudents: DashboardStaffStudents | null;
    staffCourseSchedules: DashboardStaffCourseSchedules | null;
    staffCourseOfferingForm: DashboardStaffCourseOfferingForm | null;
    staffCourseTimetable: DashboardStaffCourseTimetable | null;
    student: DashboardStudent | null;
    studentProfile: DashboardStudentProfile | null;
    studentHome: DashboardStudentHome | null;
    studentResults: DashboardStudentResults | null;
    studentDues: DashboardStudentDues | null;
    studentCourseSchedules: DashboardStudentCourseSchedules | null;
};
