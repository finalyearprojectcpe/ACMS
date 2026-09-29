export type DashboardRole = 'adviser' | 'student';

export type DashboardLevel = {
    id: number;
    title: string;
    slug: string;
    yearNumber: number;
};

export type DashboardSemester = {
    id: number;
    name: string;
    slug: string;
};

export type DashboardAcademicSession = {
    id: number;
    publicId: string;
    name: string;
    departmentName: string;
    departmentSlug: string;
    startYear: number;
    endYear: number;
};

export type DashboardStudentSetContext = {
    id: number;
    publicId: string;
    name: string;
    entrySession: DashboardAcademicSession;
    currentLevel: DashboardLevel | null;
    advisorName: string | null;
    studentCount: number;
    signupUrl: string;
    signupEnabled: boolean;
    url: string;
};

export type DashboardStudent = {
    registrationNumber: string;
    currentLevel: DashboardLevel | null;
};

export type DashboardAdviserStudent = {
    id: number;
    fullName: string;
    email: string | null;
    registrationNumber: string;
    registrationRouteValue: string;
    cgpa: number | null;
    avatar: string | null;
    currentLevel: DashboardLevel | null;
    studentSet: DashboardStudentSetContext;
    entrySession: DashboardAcademicSession | null;
    advisorName: string;
    profileUrl: string;
};

export type DashboardAdviserHome = {
    hasSets: boolean;
    requestCount: number;
    recentRequests: DashboardJoinRequestRow[];
    pendingCount: number;
    pendingRegistrations: DashboardActivityRegistration[];
    paymentCount: number;
    recentPayments: DashboardAdviserHomePayment[];
};

export type DashboardJoinRequestRow = {
    id: string;
    name: string;
    registrationNumber: string;
    set: string;
    status: 'pending' | 'approved' | 'rejected';
    emailVerified: boolean;
    requestedAt: string;
    url: string;
};

export type DashboardJoinRequestList = {
    status: 'pending' | 'reviewed';
    requests: DashboardPageRows<DashboardJoinRequestRow>;
};

export type DashboardJoinRequestDetail = DashboardJoinRequestRow & {
    email: string;
    conflict: boolean;
    reason: string | null;
    reviewedAt: string | null;
    reviewer: string | null;
    rejecting: boolean;
    canApprove: boolean;
    profileUrl: string | null;
};

export type DashboardResultStatement = {
    student: {
        name: string;
        registrationNumber: string;
        registrationRouteValue: string;
        set: string;
        adviser: string;
    };
    asOf: string;
    cgpa: number | null;
    periods: {
        level: string;
        session: string;
        semester: string;
        courses: {
            code: string;
            title: string;
            units: number;
            grade: string;
        }[];
        publishedUnits: number;
        awaitingPublication: number;
        gpa: number | null;
        cgpa: number | null;
    }[];
};

export type DashboardActivityRegistration = {
    id: number;
    studentName: string;
    studentAvatar: string | null;
    studentUrl: string;
    set: string;
    levelSemester: string;
    session: string;
    status: string;
    submittedAt: string | null;
    url: string;
};

export type DashboardAdviserHomePayment = {
    id: number;
    studentName: string;
    studentAvatar: string | null;
    studentUrl: string;
    set: string;
    dueName: string;
    dueUrl: string;
    amount: string;
    paidAt: string | null;
    receiptUrl: string | null;
};

export type DashboardPageRows<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
};

export type DashboardRegistrationActivity = {
    status: string;
    search: string;
    registrations: DashboardPageRows<DashboardActivityRegistration>;
};

export type DashboardPaymentHistory = {
    sets: { id: string; name: string }[];
    filters: { set: string | null; from: string | null; to: string | null };
    payments: DashboardPageRows<DashboardAdviserHomePayment>;
};

export type DashboardAssistant = {
    available: boolean;
    selectedId: string | null;
    conversations: { id: string; title: string; updatedAt: string }[];
    messages: {
        id: number;
        role: 'user' | 'assistant';
        content: string;
        sources: { title: string; url: string }[];
    }[];
};

export type DashboardAdviserStudents = {
    assignedSets: DashboardStudentSetContext[];
    selectedSetId: number | null;
    totalStudents: number;
    students: DashboardAdviserStudent[];
};

export type DashboardAdviserCourseSchedules = {
    selectedSet: DashboardStudentSetContext | null;
    sessions: DashboardCourseScheduleSession[];
    selectedSessionId: number | null;
    selectedSemester: DashboardCourseScheduleSemester | null;
};

export type DashboardCourseScheduleSession = DashboardAcademicSession & {
    level: DashboardLevel;
    semesters: DashboardCourseScheduleSemesterSummary[];
};

export type DashboardCourseScheduleSemesterSummary = {
    id: number;
    publicId: string;
    name: string;
    slug: string;
    maximumCourseUnits: number;
    registrationEnabled: boolean;
    resultsPublishedAt: string | null;
    courseCount: number;
};

export type DashboardCourseScheduleSemester = {
    id: number;
    publicId: string;
    name: string;
    slug: string;
    session: DashboardAcademicSession & { level: DashboardLevel };
    maximumCourseUnits: number;
    registrationEnabled: boolean;
    resultsPublishedAt: string | null;
    totalUnits: number;
    offerings: DashboardAdviserCourseOffering[];
};

export type DashboardAdviserCourseOffering = {
    id: number;
    publicId: string;
    courseId: number;
    code: string;
    urlCode: string;
    title: string;
    units: number;
    category: 'normal' | 'elective';
    prerequisites: DashboardAdviserCoursePrerequisite[];
};

export type DashboardAdviserCoursePrerequisite = {
    id: number;
    code: string;
    title: string;
};

export type DashboardAdviserCourseOfferingForm = {
    mode: 'create' | 'edit';
    set: DashboardStudentSetContext;
    academicSession: DashboardAcademicSession;
    level: DashboardLevel;
    semester: DashboardSemester & { publicId: string };
    offering: {
        id: number;
        publicId: string;
        code: string;
        urlCode: string;
        title: string;
        creditUnits: number;
        category: 'normal' | 'elective';
        prerequisiteCourseIds: number[];
    } | null;
    prerequisiteOptions: DashboardAdviserCoursePrerequisiteOption[];
};

export type DashboardAdviserAcademicSessionForm = {
    mode: 'create' | 'edit';
    set: DashboardStudentSetContext;
    levels: Array<
        DashboardLevel & {
            available: boolean;
            unavailableReason: string | null;
        }
    >;
    startYear: number;
    session: {
        id: number;
        startYear: number;
        endYear: number;
        academicYearId: number;
        hasDependents: boolean;
    } | null;
    semesterMaximumCourseUnits: {
        first: number;
        second: number;
    };
};

export type DashboardAdviserCoursePrerequisiteOption = {
    id: number;
    code: string;
    title: string;
    units: number;
    level: string;
    semester: string;
};

export type DashboardAdviserDues = {
    selectedSet: DashboardStudentSetContext | null;
    sessions: Array<{
        id: number;
        routeYears: string;
        name: string;
        level: string;
        semesters: Array<{ id: number; slug: string; name: string }>;
    }>;
    selectedPeriod:
        | { kind: 'all' }
        | { kind: 'session'; id: number }
        | { kind: 'semester'; id: number };
    periodLabel: string;
    collectedAmount: string;
    outstandingAmount: string;
    dues: DashboardAdviserDueSummary[];
    paymentSettingsUrl: string;
    hasActivePaymentAccount: boolean;
};

export type DashboardAdviserDueSummary = {
    id: number;
    publicId: string;
    title: string;
    description: string | null;
    amount: string;
    status: string;
    set: string;
    period: string;
    periodParts: {
        level: string;
        levelNumber: number;
        session: string;
        sessionStartYear: number;
        semester: string;
        semesterOrder: number;
    };
    dueDate: string | null;
    paidCount: number;
    pendingCount: number;
    waivedCount: number;
    collectedAmount: string;
    outstandingAmount: string;
    paymentAccount: DashboardPaymentAccount | null;
    detailUrl: string;
};

export type DashboardAdviserDueForm = {
    mode: 'create' | 'edit';
    selectedSetName: string | null;
    sessions: Array<{
        id: number;
        label: string;
        semesters: Array<{ id: number; name: string }>;
    }>;
    paymentAccounts: DashboardPaymentAccount[];
    paymentSettingsUrl: string;
    returnUrl: string;
    defaults: {
        studentSetId: number | null;
        academicSessionId: number | null;
        academicSessionSemesterId: number | null;
        title: string;
        description: string;
        amountNaira: string;
        dueOn: string;
    };
    due: {
        id: number;
        publicId: string;
        status: string;
        canChangePeriod: boolean;
        canChangeAmount: boolean;
    } | null;
};

export type DashboardAdviserDueDetail = {
    due: DashboardAdviserDueSummary;
    students: DashboardAdviserDueStudent[];
};

export type DashboardReceiptActions = {
    receiptStatus: string | null;
    receiptUrl: string | null;
    receiptRetryUrl: string | null;
    receiptEmailStatus: string | null;
    receiptEmailRetryUrl: string | null;
};

export type DashboardAdviserDueStudent = {
    id: number;
    publicId: string;
    studentName: string;
    studentAvatar: string | null;
    registrationNumber: string;
    amount: string;
    status: 'Paid' | 'Unpaid' | 'Waived';
    paidAt: string | null;
    waiverReason: string | null;
} & DashboardReceiptActions;

export type DashboardAdviserDueWaiverForm = {
    due: {
        id: number;
        publicId: string;
        title: string;
        amount: string;
    };
    assignment: {
        id: number;
        publicId: string;
        studentName: string;
        studentAvatar: string | null;
        registrationNumber: string;
    };
};

export type DashboardPaymentAccount = {
    id: number;
    label: string;
    businessName: string;
    bankName: string;
    accountName: string;
    accountNumber: string;
    status: string;
    verifiedAt: string | null;
};

export type DashboardPaystackBankOption = {
    code: string;
    name: string;
};

export type DashboardAdviserPaymentSettings = {
    accounts: DashboardPaymentAccount[];
};

export type DashboardAdviserPaymentAccountForm = {
    accounts: DashboardPaymentAccount[];
    bankOptions: DashboardPaystackBankOption[];
    bankOptionsError: string | null;
};

export type DashboardAdviserStudentSets = {
    isCreating: boolean;
};

export type DashboardStudentSetSettings = {
    set: DashboardStudentSetContext;
    sessions: DashboardStudentSetSession[];
};

export type DashboardStudentSetSession = DashboardAcademicSession & {
    level: DashboardLevel;
    isCurrent: boolean;
    currentSemesterId: number | null;
    semesters: DashboardSemester[];
    hasDependents: boolean;
};

export type DashboardAdviserProfile = {
    adviser: {
        title: string | null;
        name: string;
        displayName: string;
        email: string | null;
        avatar: string | null;
    };
    honorifics: Array<{
        value: string;
        label: string;
    }>;
};

export type DashboardStudentRecord = {
    student: DashboardAdviserStudent;
    entrySession: DashboardAcademicSession;
    courseAdvisor: string;
    pendingRegistrations: Array<{ label: string; url: string }>;
    results: {
        cgpa: number | null;
        columns: Array<{
            level: string;
            session: string;
            semesters: Array<{
                name: string;
                gpa: number | null;
                url: string | null;
            }>;
            cgpa: number | null;
        }>;
    };
    dues: {
        unpaid: DashboardStudentRecordDue[];
        paid: DashboardStudentRecordDue[];
        waived: DashboardStudentRecordDue[];
    };
};

export type DashboardStudentRecordDue = {
    name: string;
    period: string;
    amount: string;
    assignedAt: string | null;
    paidAt: string | null;
    waivedAt: string | null;
    waiverReason: string | null;
    receiptStatus: string | null;
    receiptUrl: string | null;
};

export type DashboardStudentRecordResults = {
    student: DashboardAdviserStudent;
    studentProfileUrl: string;
    results: DashboardStudentResults;
};

export type DashboardResultsSession = DashboardAcademicSession & {
    level: DashboardLevel;
    semesters: Array<{
        id: number;
        publicId: string;
        name: string;
        slug: string;
        registrationEnabled: boolean;
        courseCount: number;
        resultsPublishedAt: string | null;
    }>;
};

export type DashboardAdviserResults = {
    set: DashboardStudentSetContext;
    sessions: DashboardResultsSession[];
    selectedSemesterId: number | null;
    period: null | {
        level: string;
        session: string;
        semester: string;
        registrationEnabled: boolean;
        scheduleUrl: string;
        registrationsUrl: string;
    };
    courses: Array<{
        publicId: string;
        code: string;
        title: string;
        units: number;
        isElective: boolean;
        registeredCount: number;
        enteredCount: number;
        publishedAt: string | null;
        url: string;
    }>;
};

export type DashboardGradeRoster = {
    set: DashboardStudentSetContext;
    period: {
        level: string;
        session: string;
        semester: string;
    };
    course: { code: string; title: string; units: number; isElective: boolean };
    publishedAt: string | null;
    publication: {
        approvedCount: number;
        awaitingReviewCount: number;
        totalResults: number;
        enteredResults: number;
        canPublish: boolean;
        reason: string | null;
    };
    students: Array<{
        itemId: number;
        name: string;
        avatar: string | null;
        registrationNumber: string;
        profileUrl: string;
        grade: string | null;
        missingScript: boolean;
        corrections: Array<{
            from: string;
            to: string;
            reason: string;
            at: string;
        }>;
    }>;
    saveUrl: string;
    publishUrl: string;
    semesterResultsUrl: string;
};

export type DashboardStudentResults = {
    history: Array<{
        sessionYears: string;
        session: string;
        level: string;
        semester: string;
        gpa: number | null;
        cgpa: number | null;
        url: string;
    }>;
    periods: Array<{
        sessionYears: string;
        semester: string;
        label: string;
        shortLabel: string;
        gpa: number | null;
        url: string;
    }>;
    selected: null | {
        sessionYears: string;
        semester: string;
        label: string;
        gpa: number | null;
        courses: Array<{
            code: string;
            title: string;
            units: number;
            grade: string | null;
            missingScript: boolean;
            pending: boolean;
        }>;
    };
};

export type DashboardStudentProfile = {
    name: {
        full: string;
        surname: string;
        otherNames: string;
    };
    avatar: string | null;
};

export type DashboardStudentHome = {
    student: {
        name: string;
        avatar: string | null;
        registrationNumber: string;
        department: string;
        entrySet: string;
    };
    currentSemester: {
        name: string;
        level: string;
        session: string;
    } | null;
    courseAdvisor: string;
    cgpa: number | null;
    registrationStatus: string | null;
    registrationOpen: boolean;
    hasAvailableCourses: boolean;
    totalCourseUnits: number;
    courses: DashboardStudentHomeCourse[];
    pendingDues: DashboardStudentHomeDue[];
};

export type DashboardStudentHomeCourse = {
    code: string;
    title: string;
    units: number;
};

export type DashboardStudentHomeDue = DashboardStudentDue;

export type DashboardStudentDues = {
    paidAmount: string;
    totalAmount: string;
    unpaidCount: number;
    rows: DashboardStudentDueRow[];
};

export type DashboardStudentDueRow = {
    id: number;
    name: string;
    description: string | null;
    amount: string;
    status: 'Paid' | 'Unpaid' | 'Waived';
    waiverReason: string | null;
    dueDate?: string | null;
    paidAt?: string;
    paymentUrl?: string | null;
    paymentActionLabel?: string;
    paymentUnavailableReason?: string | null;
} & Partial<DashboardReceiptActions>;

export type DashboardStudentDue = {
    id: number;
    name: string;
    description: string | null;
    amount: string;
    dueDate: string | null;
    paymentUrl: string | null;
    paymentActionLabel: string;
    paymentUnavailableReason: string | null;
};

export type DashboardStudentCourseRegistration = {
    registrationOpen: boolean;
    hasAvailableCourses: boolean;
    currentSemester: {
        name: string;
        level: string;
        session: string;
        sessionYears: string;
        slug: string;
    } | null;
    selectedSemesterId: number | null;
    sessions: Array<{
        id: number;
        publicId: string;
        name: string;
        level: DashboardLevel;
        semesters: Array<{ id: number; publicId: string; name: string }>;
    }>;
    maximumCourseUnits: number;
    reviewStatus: string;
    feedbackNote: string | null;
    selectedCourses: DashboardCourseRegistrationCourse[];
    courseGroups: DashboardCourseRegistrationGroup[];
};

export type DashboardCourseRegistrationCourse = {
    courseOfferingId: number;
    code: string;
    title: string;
    units: number;
    category: string;
    categoryValue: 'normal' | 'elective';
    isElective: boolean;
    prerequisites: string[];
    missingPrerequisites: string[];
};

export type DashboardCourseRegistrationGroup = {
    key: 'normal' | 'elective';
    title: string;
    courses: DashboardCourseRegistrationCourse[];
};

export type DashboardCourseRegistrationSummary = {
    id: number;
    status: string;
    semester: {
        level: string;
        name: string;
        session: string;
    };
    totalUnits: number;
    courses: DashboardCourseRegistrationCourse[];
    reviewUrl: string;
};

export type DashboardAdviserCourseRegistration = {
    selectedSet: DashboardStudentSetContext | null;
    sessions: DashboardCourseScheduleSession[];
    selectedSessionId: number | null;
    selectedSemesterId: number | null;
    status: string;
    registrations: DashboardAdviserCourseRegistrationListItem[];
    review: DashboardCourseRegistrationReview | null;
};

export type DashboardAdviserCourseRegistrationListItem =
    DashboardCourseRegistrationSummary & {
        student: {
            name: string;
            avatar: string | null;
            registrationNumber: string;
            profileUrl: string;
        };
        submittedAt: string | null;
    };

export type DashboardCourseRegistrationReview =
    DashboardAdviserCourseRegistrationListItem & {
        routeParameters: {
            registrationNumber: string;
            sessionYears: string;
            semester: string;
        };
        set: DashboardStudentSetContext | null;
        feedbackNote: string | null;
        canReview: boolean;
    };

export type DashboardContext = {
    role: DashboardRole;
    roleLabel: string;
    section: string;
    sectionTitle: string;
    advisedSets: DashboardStudentSetContext[];
    activeStudentSetId: number | null;
    adviserHome: DashboardAdviserHome | null;
    adviserJoinRequests: DashboardJoinRequestList | null;
    adviserJoinRequest: DashboardJoinRequestDetail | null;
    adviserRegistrationActivity: DashboardRegistrationActivity | null;
    adviserPaymentHistory: DashboardPaymentHistory | null;
    adviserAssistant: DashboardAssistant | null;
    adviserStudentSets: DashboardAdviserStudentSets | null;
    studentSetSettings: DashboardStudentSetSettings | null;
    adviserStudents: DashboardAdviserStudents | null;
    adviserCourseSchedules: DashboardAdviserCourseSchedules | null;
    adviserAcademicSessionForm: DashboardAdviserAcademicSessionForm | null;
    adviserCourseOfferingForm: DashboardAdviserCourseOfferingForm | null;
    adviserCourseRegistration: DashboardAdviserCourseRegistration | null;
    adviserResults: DashboardAdviserResults | null;
    adviserGradeRoster: DashboardGradeRoster | null;
    adviserDues: DashboardAdviserDues | null;
    adviserDueForm: DashboardAdviserDueForm | null;
    adviserDueDetail: DashboardAdviserDueDetail | null;
    adviserDueWaiverForm: DashboardAdviserDueWaiverForm | null;
    adviserPaymentSettings: DashboardAdviserPaymentSettings | null;
    adviserPaymentAccountForm: DashboardAdviserPaymentAccountForm | null;
    adviserProfile: DashboardAdviserProfile | null;
    studentRecord: DashboardStudentRecord | null;
    studentRecordResults: DashboardStudentRecordResults | null;
    studentRecordStatement: DashboardResultStatement | null;
    student: DashboardStudent | null;
    studentProfile: DashboardStudentProfile | null;
    studentHome: DashboardStudentHome | null;
    studentDues: DashboardStudentDues | null;
    studentCourseRegistration: DashboardStudentCourseRegistration | null;
    studentResults: DashboardStudentResults | null;
};
