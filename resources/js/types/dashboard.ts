export type DashboardRole = 'adviser' | 'student';

export type DashboardProgramme = {
    id: number;
    name: string;
    abbreviation: string;
    minimumYearsToDegree: number;
};

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
    name: string;
    startsOn: string;
    endsOn: string;
    status: 'Current' | 'Upcoming' | 'Past';
};

export type DashboardStudentSetContext = {
    id: number;
    name: string;
    entrySession: DashboardAcademicSession;
    currentLevel: DashboardLevel | null;
    advisorName: string | null;
    minimumYearsToDegree: number;
    maximumCourseUnits: number;
    isGraduated: boolean;
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
    avatar: string | null;
    currentLevel: DashboardLevel | null;
    studentSet: DashboardStudentSetContext;
    entrySession: DashboardAcademicSession | null;
    advisorName: string;
    profileUrl: string;
    accountStatus: string;
    accessCode: string | null;
};

export type DashboardAdviserHome = {
    assignedSets: DashboardStudentSetContext[];
    levels: DashboardLevel[];
    students: DashboardAdviserStudent[];
    totalStudents: number;
    maximumCourseUnits: number;
    pendingDuesByStudent: DashboardAdviserHomeStudentDueSummary[];
    recentPayments: DashboardAdviserHomePayment[];
};

export type DashboardAdviserHomeStudentDueSummary = {
    studentName: string;
    studentAvatar: string | null;
    registrationNumber: string;
    dues: string[];
    outstandingAmount: string;
};

export type DashboardAdviserHomePayment = {
    studentName: string;
    studentAvatar: string | null;
    dueName: string;
    amount: string;
    paidAt: string;
};

export type DashboardAdviserStudents = {
    programme: DashboardProgramme;
    assignedSets: DashboardStudentSetContext[];
    totalStudents: number;
    students: DashboardAdviserStudent[];
};

export type DashboardAdviserStudentForm = {
    mode: 'create' | 'import';
    programme: DashboardProgramme;
    assignedSets: DashboardStudentSetContext[];
    suggestedAccessCode: string;
};

export type DashboardAdviserCourseSchedules = {
    level: DashboardLevel | null;
    levels: DashboardLevel[];
    academicSession: DashboardAcademicSession | null;
    selectedSet: DashboardStudentSetContext | null;
    assignedSets: DashboardStudentSetContext[];
    semesters: DashboardAdviserCourseScheduleSemester[];
};

export type DashboardAdviserCourseScheduleSemester = {
    id: string;
    name: string;
    slug: string;
    totalUnits: number;
    normalCourseCount: number;
    electiveCourseCount: number;
    offerings: DashboardAdviserCourseOffering[];
};

export type DashboardAdviserCourseOffering = {
    id: number;
    courseId: number;
    code: string;
    title: string;
    units: number;
    category: 'normal' | 'elective';
    prerequisites: DashboardAdviserCoursePrerequisite[];
    canEdit: boolean;
};

export type DashboardAdviserCoursePrerequisite = {
    id: number;
    code: string;
    title: string;
};

export type DashboardAdviserCourseOfferingForm = {
    mode: 'create' | 'edit';
    academicSession: DashboardAcademicSession;
    level: DashboardLevel;
    semester: DashboardSemester;
    offering: {
        id: number;
        code: string;
        title: string;
        creditUnits: number;
        category: 'normal' | 'elective';
        prerequisiteCourseIds: number[];
    } | null;
    studentSetId: number | null;
    prerequisiteOptions: DashboardAdviserCoursePrerequisiteOption[];
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
    assignedSets: DashboardStudentSetContext[];
    metrics: {
        expectedAmount: string;
        collectedAmount: string;
        outstandingAmount: string;
        studentsOwing: number;
    };
    dues: DashboardAdviserDueSummary[];
    createUrl: string;
    paymentSettingsUrl: string;
    hasActivePaymentAccount: boolean;
};

export type DashboardAdviserDueSummary = {
    id: number;
    title: string;
    description: string | null;
    amount: string;
    status: string;
    set: string;
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
    assignedSets: DashboardStudentSetContext[];
    paymentAccounts: DashboardPaymentAccount[];
    paymentSettingsUrl: string;
    defaults: {
        studentSetId: number | null;
        title: string;
        description: string;
        amountNaira: string;
        dueOn: string;
    };
    due: {
        id: number;
        status: string;
        canChangeSet: boolean;
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
    studentName: string;
    studentAvatar: string | null;
    registrationNumber: string;
    amount: string;
    status: string;
    paidAt: string | null;
    waiverReason: string | null;
} & DashboardReceiptActions;

export type DashboardAdviserDueWaiverForm = {
    due: {
        id: number;
        title: string;
        amount: string;
    };
    assignment: {
        id: number;
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
    set: string;
    entryAcademicSessionId: number | null;
    verifiedAt: string | null;
};

export type DashboardPaystackBankOption = {
    code: string;
    name: string;
};

export type DashboardAdviserPaymentSettings = {
    accounts: DashboardPaymentAccount[];
    dashboardUrl: string;
    createUrl: string;
};

export type DashboardAdviserPaymentAccountForm = {
    accounts: DashboardPaymentAccount[];
    bankOptions: DashboardPaystackBankOption[];
    bankOptionsError: string | null;
    dashboardUrl: string;
    returnUrl: string;
};

export type DashboardAdviserSettings = {
    adviser: {
        name: string;
        email: string | null;
        avatar: string | null;
    };
    institution: string | null;
    programme: DashboardProgramme;
    assignedSets: DashboardStudentSetContext[];
    academicSessions: DashboardAcademicSession[];
    setupForm: DashboardAcademicSetupForm | null;
};

export type DashboardAcademicSetupForm =
    | {
          kind: 'academic-session';
          mode: 'create' | 'edit';
          id: number | null;
          name: string;
          startsOn: string;
          endsOn: string;
      }
    | {
          kind: 'student-set';
          mode: 'create' | 'edit';
          id: number | null;
          entryAcademicSessionId: number | null;
          minimumYearsToDegree: number;
          maximumCourseUnits: number;
      };

export type DashboardStudentRecord = {
    student: DashboardAdviserStudent;
    institution: string | null;
    programme: DashboardProgramme;
    entrySession: DashboardAcademicSession;
    courseAdvisor: string;
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
    institution: string | null;
    programme: DashboardProgramme;
    currentLevel: DashboardLevel | null;
    courseAdvisor: string;
};

export type DashboardStudentHome = {
    currentSemester: {
        name: string;
        level: string;
        session: string;
    } | null;
    courseAdvisor: string;
    totalCourseUnits: number;
    courses: DashboardStudentHomeCourse[];
    pendingDues: DashboardStudentHomeDue[];
};

export type DashboardStudentHomeCourse = {
    code: string;
    title: string;
    units: number;
    type: string;
};

export type DashboardStudentHomeDue = {
    title: string;
    context: string;
    amount: string;
    dueDate: string;
};

export type DashboardStudentDues = {
    pendingAmount: string;
    paidAmount: string;
    totalDues: number;
    pending: DashboardStudentDue[];
    paid: DashboardStudentPaidDue[];
};

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

export type DashboardStudentPaidDue = {
    id: number;
    name: string;
    description: string | null;
    amount: string;
    paidAt: string;
} & DashboardReceiptActions;

export type DashboardStudentCourseRegistration = {
    currentSemester: {
        name: string;
        level: string;
        session: string;
    };
    maximumCourseUnits: number;
    advisorName: string;
    reviewStatus: string;
    feedbackNote: string | null;
    registrationId: number | null;
    selectedUnits: number;
    selectedCourses: DashboardCourseRegistrationCourse[];
    availableCourses: DashboardCourseRegistrationCourse[];
    selectableCourses: DashboardCourseRegistrationCourse[];
    courseGroups: DashboardCourseRegistrationGroup[];
    previousRegistrations: DashboardCourseRegistrationSummary[];
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
    assignedSets: DashboardStudentSetContext[];
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
        set: DashboardStudentSetContext | null;
        programme: DashboardProgramme;
        feedbackNote: string | null;
        canReview: boolean;
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
    advisedSets: DashboardStudentSetContext[];
    programme: DashboardProgramme | null;
    adviserHome: DashboardAdviserHome | null;
    adviserStudents: DashboardAdviserStudents | null;
    adviserStudentForm: DashboardAdviserStudentForm | null;
    adviserCourseSchedules: DashboardAdviserCourseSchedules | null;
    adviserCourseOfferingForm: DashboardAdviserCourseOfferingForm | null;
    adviserCourseRegistration: DashboardAdviserCourseRegistration | null;
    adviserDues: DashboardAdviserDues | null;
    adviserDueForm: DashboardAdviserDueForm | null;
    adviserDueDetail: DashboardAdviserDueDetail | null;
    adviserDueWaiverForm: DashboardAdviserDueWaiverForm | null;
    adviserPaymentSettings: DashboardAdviserPaymentSettings | null;
    adviserPaymentAccountForm: DashboardAdviserPaymentAccountForm | null;
    adviserSettings: DashboardAdviserSettings | null;
    studentRecord: DashboardStudentRecord | null;
    student: DashboardStudent | null;
    studentProfile: DashboardStudentProfile | null;
    studentHome: DashboardStudentHome | null;
    studentDues: DashboardStudentDues | null;
    studentCourseRegistration: DashboardStudentCourseRegistration | null;
};
