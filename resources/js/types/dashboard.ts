export type DashboardRole =
    | 'school_admin'
    | 'student'
    | 'staff'
    | 'hod'
    | 'faculty_head';

export type DashboardDepartment = {
    id?: number;
    name: string;
    abbreviation: string;
    minimumYearsToDegree?: number;
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

export type DashboardFacultyHead = {
    metrics: {
        departments: number;
        staff: number;
        activeStaff: number;
        pendingSignups: number;
        students: number;
        departmentsWithoutHod: number;
        hodCoverage: string;
    };
    departments: DashboardFacultyHeadDepartment[];
    pendingAccessCodes: DashboardFacultyHeadAccessCode[];
    staff: DashboardFacultyHeadStaff[];
    pendingTasks: DashboardFacultyHeadTask[];
    courseSetupSession: DashboardAcademicSession | null;
    courseSetup: DashboardFacultyHeadCourseSetup[];
    settings: DashboardFacultyHeadSettings;
};

export type DashboardFacultyHeadSettings = {
    faculty: {
        name: string;
        abbreviation: string;
    };
    head: {
        name: string;
        email: string | null;
        avatar: string | null;
    };
    pendingOwnershipTransfer: DashboardFacultyHeadTransfer | null;
};

export type DashboardFacultyHeadTransfer = {
    id: number;
    recipientEmail: string;
    recipientName: string | null;
    accessCode: string;
    expiresAt: string;
};

export type DashboardFacultyHeadDepartment = {
    id: number;
    name: string;
    abbreviation: string;
    status: string;
    staffCount: number;
    studentCount: number;
    hod: {
        name: string;
        email: string | null;
        avatar: string | null;
        accessCode: string | null;
        activatedAt: string | null;
    } | null;
};

export type DashboardFacultyHeadAccessCode = {
    id: number;
    name: string;
    email: string | null;
    avatar: string | null;
    accessCode: string | null;
    department: string | null;
    role: string;
    roleContext: string | null;
    status: string;
};

export type DashboardFacultyHeadTask = {
    id: string;
    title: string;
    detail: string;
    status: string;
};

export type DashboardFacultyHeadCourseSetup = {
    id: string;
    label: string;
    courseCount: number;
    totalUnits: number;
    status: string;
};

export type DashboardFacultyHeadHodForm = {
    mode: 'assign' | 'import';
    selectedStaffProfileId: number | null;
    selectedDepartmentId: number | null;
    department: DashboardFacultyHeadHodDepartmentContext | null;
    departments: DashboardFacultyHeadHodFormDepartment[];
    staff: DashboardFacultyHeadStaff[];
    suggestedAccessCode: string;
};

export type DashboardFacultyHeadHodDepartmentContext = {
    id: number;
    name: string;
    abbreviation: string;
    currentHod: {
        name: string;
        email: string | null;
        avatar: string | null;
    } | null;
};

export type DashboardFacultyHeadHodFormDepartment = {
    id: number;
    name: string;
    abbreviation: string;
    currentHod: string | null;
};

export type DashboardFacultyHeadStaffForm = {
    mode: 'create' | 'import';
    departments: DashboardFacultyHeadStaffFormDepartment[];
    suggestedAccessCode: string;
};

export type DashboardFacultyHeadStaffFormDepartment = {
    id: number;
    name: string;
    abbreviation: string;
};

export type DashboardFacultyHeadStaff = {
    id: number;
    staffProfileId: number;
    name: string;
    email: string | null;
    avatar: string | null;
    department: DashboardDepartment | null;
    faculty: DashboardFaculty | null;
    role: string;
    roleContext: string | null;
    status: string;
    accessCode: string | null;
    canAssignHod: boolean;
    assignmentBlockReason: string | null;
};

export type DashboardAcademicSession = {
    id: number;
    name: string;
    startsOn: string;
    endsOn: string;
    status: 'Current' | 'Upcoming' | 'Past';
};

export type DashboardSchoolAdmin = {
    school: {
        name: string;
        abbreviation: string;
        slug: string;
    };
    metrics: {
        faculties: number;
        departments: number;
        academicSessions: number;
        facultyHeadCoverage: string;
    };
    currentAcademicSession: DashboardAcademicSession | null;
    nextAcademicSession: DashboardAcademicSession | null;
    academicSessions: DashboardAcademicSession[];
    faculties: DashboardSchoolAdminFaculty[];
    pendingAccessCodes: DashboardFacultyHeadAccessCode[];
    settings: DashboardSchoolAdminSettings;
};

export type DashboardSchoolAdminSettings = {
    admin: {
        name: string;
        email: string | null;
        avatar: string | null;
    };
    pendingOwnershipTransfer: DashboardSchoolAdminTransfer | null;
};

export type DashboardSchoolAdminTransfer = {
    id: number;
    recipientEmail: string;
    recipientName: string | null;
    accessCode: string;
    expiresAt: string;
};

export type DashboardSchoolAdminFaculty = {
    id: number;
    name: string;
    abbreviation: string;
    slug: string;
    departmentsCount: number;
    head: {
        name: string;
        email: string | null;
        avatar: string | null;
        department: DashboardDepartment | null;
        accessCode: string | null;
        activatedAt: string | null;
    } | null;
    departments: DashboardSchoolAdminDepartment[];
};

export type DashboardSchoolAdminDepartment = {
    id: number;
    name: string;
    abbreviation: string;
    minimumYearsToDegree: number;
    hod: {
        name: string;
        email: string | null;
        avatar: string | null;
    } | null;
};

export type DashboardSchoolAdminAcademicSessionForm = {
    mode: 'create' | 'edit';
    academicSession: DashboardAcademicSession | null;
};

export type DashboardSchoolAdminFacultyForm = {
    mode: 'create' | 'edit';
    faculty: {
        id: number;
        name: string;
        abbreviation: string;
    } | null;
};

export type DashboardSchoolAdminDepartmentForm = {
    mode: 'create' | 'edit';
    selectedFacultyId: number | null;
    department: {
        id: number;
        facultyId: number;
        name: string;
        abbreviation: string;
        minimumYearsToDegree: number;
    } | null;
    faculties: DashboardSchoolAdminFacultyOption[];
};

export type DashboardSchoolAdminFacultyHeadForm = {
    selectedFacultyId: number | null;
    suggestedAccessCode: string;
    faculties: DashboardSchoolAdminFacultyHeadFaculty[];
};

export type DashboardSchoolAdminFacultyOption = {
    id: number;
    name: string;
    abbreviation: string;
};

export type DashboardSchoolAdminFacultyHeadFaculty =
    DashboardSchoolAdminFacultyOption & {
        departments: DashboardSchoolAdminFacultyHeadDepartment[];
    };

export type DashboardSchoolAdminFacultyHeadDepartment = {
    id: number;
    name: string;
    abbreviation: string;
};

export type DashboardFacultyHeadCourses = {
    academicSession: DashboardAcademicSession | null;
    academicSessions: DashboardAcademicSession[];
    copySourceSession: DashboardAcademicSession | null;
    canManageSelectedSession: boolean;
    metrics: {
        departments: number;
        courses: number;
        compulsoryUnits: number;
        session: string;
    };
    levels: DashboardLevel[];
    semesters: DashboardFacultyHeadCourseSemester[];
};

export type DashboardFacultyHeadCourseSemester = {
    id: string;
    level: DashboardLevel;
    semester: DashboardStaffCourseSemester;
    totalUnits: number;
    courseCount: number;
    offerings: DashboardFacultyHeadCourseOffering[];
};

export type DashboardFacultyHeadCourseOffering = {
    id: number;
    courseId: number;
    code: string;
    title: string;
    units: number;
    departmentsCount: number;
    prerequisites: DashboardStaffCoursePrerequisite[];
};

export type DashboardFacultyHeadCourseOfferingForm = {
    mode: 'create' | 'edit';
    academicSession: DashboardAcademicSession;
    level: DashboardLevel;
    semester: DashboardStaffCourseSemester;
    offering: {
        id: number;
        code: string;
        title: string;
        creditUnits: number;
        prerequisiteCourseIds: number[];
    } | null;
    prerequisiteOptions: DashboardStaffCoursePrerequisiteOption[];
};

export type DashboardStaffHome = {
    assignedSets: DashboardStudentSetContext[];
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
    studentAvatar: string | null;
    registrationNumber: string;
    requestType: string;
    submittedAt: string;
};

export type DashboardStaffHomeStudentDueSummary = {
    studentName: string;
    studentAvatar: string | null;
    registrationNumber: string;
    dues: string[];
    outstandingAmount: string;
};

export type DashboardStaffHomePayment = {
    studentName: string;
    studentAvatar: string | null;
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
    department: DashboardDepartment | null;
    assignedSets: DashboardStudentSetContext[];
    totalStudents: number;
    students: DashboardStaffStudent[];
};

export type DashboardStudentSetContext = {
    entrySession: DashboardAcademicSession;
    currentLevel: DashboardLevel | null;
    advisorName: string | null;
    minimumYearsToDegree: number;
};

export type DashboardStaffStudent = {
    id: number;
    fullName: string;
    email: string;
    registrationNumber: string;
    avatar: string | null;
    currentLevel: DashboardLevel | null;
    entrySession: DashboardAcademicSession | null;
    advisorName: string;
    pendingCarryovers: string[];
    levelGpas: DashboardStaffStudentLevelGpa[];
    cgpa: string;
    profileUrl: string;
};

export type DashboardStaffStudentLevelGpa = {
    level: string;
    gpa: string;
};

export type DashboardHod = {
    department: DashboardDepartment;
    faculty: DashboardFaculty | null;
    metrics: {
        students: number;
        activeStaff: number;
        pendingSignups: number;
        setsWithoutAdvisors: number;
        resultsAwaitingReview: number;
        missingResults: number;
        studentsWithCarryovers: number;
    };
    staff: DashboardHodStaff[];
    students: DashboardStaffStudent[];
    levels: DashboardLevel[];
    setAdvisors: DashboardHodSetAdvisor[];
    resultSubmissions: DashboardHodResultSubmission[];
    carryoverSummaries: DashboardStaffHomeCarryoverSummary[];
    academicSessions: DashboardAcademicSession[];
    courseOfferings: DashboardHodCourseOfferings;
    studentResults: DashboardHodStudentResults;
    settings: {
        hod: {
            name: string;
            email: string | null;
            avatar: string | null;
        };
    };
};

export type DashboardHodStaff = {
    id: number;
    staffProfileId: number;
    name: string;
    email: string | null;
    avatar: string | null;
    role: string;
    roleContext: string | null;
    status: string;
    accessCode: string | null;
    assignedSets: DashboardStudentSetContext[];
};

export type DashboardHodSetAdvisor = {
    entrySession: DashboardAcademicSession;
    currentLevel: DashboardLevel | null;
    advisor: {
        name: string;
        email: string | null;
        avatar: string | null;
    } | null;
    studentsCount: number;
};

export type DashboardHodResultSubmission = {
    id: string;
    level: string;
    semester: string;
    course: string;
    status: string;
    submittedAt: string;
};

export type DashboardHodCourseOfferings = {
    academicSession: DashboardAcademicSession | null;
    level: DashboardLevel | null;
    semesters: DashboardStaffCourseScheduleSemester[];
};

export type DashboardHodStaffForm = {
    mode: 'import';
    department: {
        id: number;
        name: string;
        abbreviation: string;
    };
    suggestedAccessCode: string;
};

export type DashboardHodStudentResults = {
    averageCgpa: string;
    studentsCount: number;
    carryoverStudents: number;
    pendingSubmissions: number;
    sets: DashboardAcademicSession[];
};

export type DashboardStudentRecord = {
    student: DashboardStaffStudent;
    faculty: DashboardFaculty | null;
    department: DashboardDepartment | null;
    entrySession: DashboardAcademicSession | null;
    courseAdvisor: string;
    cgpa: string;
    levelGpas: DashboardStaffStudentLevelGpa[];
    semesters: DashboardStudentRecordSemester[];
};

export type DashboardStudentRecordSemester = {
    id: string;
    level: DashboardLevel;
    semester: DashboardStaffCourseSemester;
    gpa: string;
    totalUnits: number;
    courses: DashboardStudentResultCourse[];
};

export type DashboardStaffCourseSchedules = {
    level: DashboardLevel;
    academicSession: {
        name: string;
    } | null;
    semesters: DashboardStaffCourseScheduleSemester[];
};

export type DashboardStaffCourseScheduleSemester = {
    id: string;
    name: string;
    slug: string;
    totalUnits: number;
    facultyCourseCount: number;
    departmentalCourseCount: number;
    electiveCourseCount: number;
    offerings: DashboardStaffCourseOffering[];
};

export type DashboardStaffCourseOffering = {
    id: number;
    courseId: number;
    code: string;
    title: string;
    units: number;
    isMandatory: boolean;
    courseType: string;
    prerequisites: DashboardStaffCoursePrerequisite[];
    canEdit: boolean;
};

export type DashboardStaffCoursePrerequisite = {
    id: number;
    code: string;
    title: string;
};

export type DashboardStaffCourseOfferingForm = {
    mode: 'create' | 'edit';
    academicSession: DashboardAcademicSession;
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

export type DashboardStaffCourseSemester = {
    id: number;
    name: string;
    slug: string;
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
    courses: DashboardStudentHomeCourse[];
    pendingDues: DashboardStudentHomeDue[];
    resits: DashboardStudentHomeResit[];
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
    advisedSets: DashboardStudentSetContext[];
    department: DashboardDepartment | null;
    faculty: DashboardFaculty | null;
    schoolAdmin: DashboardSchoolAdmin | null;
    schoolAdminAcademicSessionForm: DashboardSchoolAdminAcademicSessionForm | null;
    schoolAdminFacultyForm: DashboardSchoolAdminFacultyForm | null;
    schoolAdminDepartmentForm: DashboardSchoolAdminDepartmentForm | null;
    schoolAdminFacultyHeadForm: DashboardSchoolAdminFacultyHeadForm | null;
    facultyHead: DashboardFacultyHead | null;
    facultyHeadHodForm: DashboardFacultyHeadHodForm | null;
    facultyHeadStaffForm: DashboardFacultyHeadStaffForm | null;
    facultyHeadCourses: DashboardFacultyHeadCourses | null;
    facultyHeadCourseOfferingForm: DashboardFacultyHeadCourseOfferingForm | null;
    hod: DashboardHod | null;
    hodStaffForm: DashboardHodStaffForm | null;
    staffHome: DashboardStaffHome | null;
    staffStudents: DashboardStaffStudents | null;
    staffCourseSchedules: DashboardStaffCourseSchedules | null;
    staffCourseOfferingForm: DashboardStaffCourseOfferingForm | null;
    studentRecord: DashboardStudentRecord | null;
    student: DashboardStudent | null;
    studentProfile: DashboardStudentProfile | null;
    studentHome: DashboardStudentHome | null;
    studentResults: DashboardStudentResults | null;
    studentDues: DashboardStudentDues | null;
    studentCourseSchedules: DashboardStudentCourseSchedules | null;
};
