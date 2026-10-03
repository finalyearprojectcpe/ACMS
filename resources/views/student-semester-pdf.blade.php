<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 38px 42px; }
        body { margin: 0; color: #111; font-family: 'DejaVu Sans', sans-serif; font-size: 9px; line-height: 1.5; }
        h1 { margin: 0 0 5px; font-size: 19px; font-weight: 700; line-height: 1.2; }
        .period { color: #444; font-size: 10px; }
        .identity { width: 100%; margin: 26px 0 22px; border-collapse: collapse; }
        .identity td { width: 50%; padding: 0 16px 12px 0; vertical-align: top; }
        .label { display: block; margin-bottom: 2px; color: #666; font-size: 7px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; }
        .value { display: block; font-size: 10px; font-weight: 700; }
        .context { margin: 0 0 10px; color: #555; font-size: 8px; }
        .courses { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .courses thead { display: table-header-group; }
        .courses th { padding: 8px 7px; border-top: 1.5px solid #111; border-bottom: 1px solid #111; text-align: left; font-size: 7px; text-transform: uppercase; letter-spacing: .4px; }
        .courses td { padding: 9px 7px; border-bottom: .5px solid #bbb; vertical-align: top; }
        .courses tr { page-break-inside: avoid; }
        .courses th:first-child, .courses td:first-child { padding-left: 0; }
        .courses th:last-child, .courses td:last-child { padding-right: 0; }
        .code { font-weight: 700; white-space: nowrap; }
        .course-kind { display: block; margin-top: 2px; color: #666; font-size: 7px; }
        .number, .grade { text-align: right; white-space: nowrap; }
        .total td { border-top: 1px solid #111; border-bottom: 1.5px solid #111; font-weight: 700; }
        .summary { margin-top: 16px; text-align: right; font-size: 10px; }
        .summary strong { margin-left: 8px; font-size: 12px; }
    </style>
</head>
<body>
    <h1>{{ $title }}</h1>
    <div class="period">{{ $period }}</div>

    <table class="identity" aria-label="Student details">
        <tr>
            <td><span class="label">Student</span><span class="value">{{ $student->official_name }}</span></td>
            <td><span class="label">Registration Number</span><span class="value">{{ $student->registration_number }}</span></td>
        </tr>
        <tr>
            <td><span class="label">Student Set</span><span class="value">{{ $student->studentSet->label() }}</span></td>
            <td><span class="label">Course Adviser</span><span class="value">{{ $student->advisorProfile->user->name }}</span></td>
        </tr>
    </table>

    @if ($results)
        <p class="context">Results available as of {{ now()->format('j M Y') }}</p>
    @endif
    <table class="courses" aria-label="{{ $title }}">
        <thead>
            <tr>
                <th style="width: 16%">Course</th>
                <th>Title</th>
                <th style="width: 10%; text-align: right">Units</th>
                @if ($results)
                    <th style="width: 17%; text-align: right">Grade</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @foreach ($courses as $course)
                <tr>
                    <td class="code">{{ $course['code'] }}</td>
                    <td>
                        {{ $course['title'] }}
                        @if ($course['assignmentOrigin'] === 'carryover')
                            <span class="course-kind">Carryover</span>
                        @elseif ($course['isElective'])
                            <span class="course-kind">Elective</span>
                        @endif
                    </td>
                    <td class="number">{{ $course['units'] }}</td>
                    @if ($results)
                        <td class="grade">{{ $course['pending'] ? 'Awaiting result' : ($course['missingScript'] ? 'Missing script' : ($course['grade'] ?? '—')) }}</td>
                    @endif
                </tr>
            @endforeach
            <tr class="total">
                <td colspan="2">Total Units</td>
                <td class="number">{{ collect($courses)->sum('units') }}</td>
                @if ($results)
                    <td></td>
                @endif
            </tr>
        </tbody>
    </table>
    @if ($results)
        <div class="summary">Semester GPA <strong>{{ $gpa === null ? 'Pending' : number_format($gpa, 2) }}</strong></div>
    @endif
</body>
</html>
