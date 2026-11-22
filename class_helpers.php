<?php
/** Shared helpers for the class pages. Requires $conn from db_connect.php. */

const CLASS_STATUSES = ['Draft', 'Open', 'Ongoing', 'Completed', 'Cancelled'];
const TEACHER_ROLES  = ['Lead Instructor', 'Co-Teacher', 'Substitute'];   // matches class_teacher.assigned_role
const MAX_TEACHERS_PER_CLASS = 10;

/**
 * Teachers for the dropdowns: all active teachers, plus any inactive teacher already
 * assigned to $class_id (so editing a class never silently drops them).
 */
function get_active_teachers(mysqli $conn, int $class_id = 0): array
{
    $stmt = $conn->prepare(
        "SELECT t.teacher_id,
                CONCAT(t.first_name, ' ', IFNULL(CONCAT(LEFT(t.middle_name, 1), '. '), ''), t.last_name,
                       IF(t.is_active = 1, '', ' (inactive)')) AS full_name
           FROM teachers t
          WHERE t.is_active = 1
             OR EXISTS (SELECT 1 FROM class_teacher ct WHERE ct.class_id = ? AND ct.teacher_id = t.teacher_id)
       ORDER BY t.last_name, t.first_name"
    );
    $stmt->bind_param('i', $class_id);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

function teacher_options(array $teachers, $selected_id, string $placeholder = '— Select teacher —'): string
{
    $html = '<option value="">' . htmlspecialchars($placeholder) . '</option>';
    foreach ($teachers as $t) {
        $sel = ((string)$selected_id === (string)$t['teacher_id']) ? ' selected' : '';
        $html .= '<option value="' . (int)$t['teacher_id'] . '"' . $sel . '>'
               . htmlspecialchars($t['full_name']) . '</option>';
    }
    return $html;
}

function role_options(string $selected): string
{
    $html = '';
    foreach (TEACHER_ROLES as $r) {
        $html .= '<option value="' . $r . '"' . ($r === $selected ? ' selected' : '') . '>' . $r . '</option>';
    }
    return $html;
}

/**
 * HTML block: a repeatable "Teacher + Role" list with an "Add another teacher" button.
 * $assigned = [ ['teacher_id' => '3', 'role' => 'Lead Instructor'], ... ]
 * Posts as teacher_id[] and teacher_role[].
 */
function teacher_assignment_section(array $teachers, array $assigned): string
{
    if (!$assigned) {
        $assigned = [['teacher_id' => '', 'role' => 'Lead Instructor']];
    }
    $row = static function ($tid, string $role) use ($teachers): string {
        return '<div class="tchr-row">'
             . '<select name="teacher_id[]" aria-label="Teacher">' . teacher_options($teachers, $tid) . '</select>'
             . '<select name="teacher_role[]" aria-label="Role">' . role_options($role) . '</select>'
             . '<button type="button" class="tchr-remove" aria-label="Remove teacher" title="Remove">&times;</button>'
             . '</div>';
    };

    $rows = '';
    foreach ($assigned as $a) {
        $rows .= $row($a['teacher_id'] ?? '', $a['role'] ?? 'Co-Teacher');
    }

    $html  = '<div class="cls-field tchr-section" style="margin-top:14px">';
    $html .= '<label>Teachers</label>';
    $html .= '<div class="tchr-list" id="teacherList">' . $rows . '</div>';
    $html .= '<button type="button" class="cls-btn ghost" id="addTeacherBtn" style="margin-top:8px">+ Add another teacher</button>';
    $html .= '<div class="hint">Pick a teacher and their role. One Lead Instructor per class; add co-teachers or substitutes as needed.'
           . (!$teachers ? ' <strong>No teacher accounts yet.</strong>' : '') . '</div>';
    $html .= '<template id="teacherRowTpl">' . $row('', 'Co-Teacher') . '</template>';
    $html .= <<<'JS'
<script>
(function () {
    const list = document.getElementById('teacherList'),
          tpl  = document.getElementById('teacherRowTpl'),
          add  = document.getElementById('addTeacherBtn');
    add.addEventListener('click', () => {
        if (list.children.length >= 10) return;
        list.appendChild(tpl.content.cloneNode(true));
    });
    list.addEventListener('click', (e) => {
        const b = e.target.closest('.tchr-remove');
        if (!b) return;
        const r = b.closest('.tchr-row');
        if (list.children.length > 1) r.remove(); else r.querySelector('select').value = '';
    });
})();
</script>
JS;
    $html .= '</div>';
    return $html;
}

/** Raw rows from POST, used only to refill the form after an error. */
function collect_teacher_rows(array $post): array
{
    $ids   = is_array($post['teacher_id']   ?? null) ? $post['teacher_id']   : [];
    $roles = is_array($post['teacher_role'] ?? null) ? $post['teacher_role'] : [];
    $out = [];
    foreach ($ids as $i => $id) {
        $out[] = ['teacher_id' => trim((string)$id), 'role' => trim((string)($roles[$i] ?? 'Co-Teacher'))];
    }
    return $out;
}

/**
 * Validates the submitted teacher rows.
 * Returns [assignments, errorMessage|null]; assignments = [['teacher_id'=>int,'role'=>string], ...]
 * Blank rows are ignored. $class_id lets an already-assigned inactive teacher stay assigned.
 */
function parse_teacher_assignments(mysqli $conn, array $post, int $class_id = 0): array
{
    $out = [];
    $seen = [];
    $leads = 0;

    foreach (collect_teacher_rows($post) as $r) {
        if ($r['teacher_id'] === '') {
            continue;
        }
        if (!ctype_digit($r['teacher_id'])) {
            return [[], 'Please choose a valid teacher.'];
        }
        if (!in_array($r['role'], TEACHER_ROLES, true)) {
            return [[], 'Please choose a valid role for each teacher.'];
        }
        $tid = (int)$r['teacher_id'];
        if (isset($seen[$tid])) {
            return [[], 'The same teacher is listed more than once.'];
        }
        $seen[$tid] = true;
        if ($r['role'] === 'Lead Instructor') {
            $leads++;
        }
        $out[] = ['teacher_id' => $tid, 'role' => $r['role']];
    }

    if (count($out) > MAX_TEACHERS_PER_CLASS) {
        return [[], 'A class can have at most ' . MAX_TEACHERS_PER_CLASS . ' teachers.'];
    }
    if ($leads > 1) {
        return [[], 'A class can have only one Lead Instructor.'];
    }

    if ($out) {
        $ids = array_column($out, 'teacher_id');
        $in  = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $conn->prepare(
            "SELECT COUNT(*) FROM teachers t
              WHERE t.teacher_id IN ($in)
                AND (t.is_active = 1
                     OR EXISTS (SELECT 1 FROM class_teacher ct WHERE ct.class_id = ? AND ct.teacher_id = t.teacher_id))"
        );
        $params = array_merge($ids, [$class_id]);
        $stmt->bind_param(str_repeat('i', count($params)), ...$params);
        $stmt->execute();
        $valid = (int)$stmt->get_result()->fetch_row()[0];
        $stmt->close();
        if ($valid !== count($ids)) {
            return [[], 'One of the selected teachers was not found or is inactive.'];
        }
    }
    return [$out, null];
}

/** Replaces all teacher rows of a class with $assignments (call inside a transaction). */
function save_class_teachers(mysqli $conn, int $class_id, array $assignments): void
{
    $stmt = $conn->prepare('DELETE FROM class_teacher WHERE class_id = ?');
    $stmt->bind_param('i', $class_id);
    $stmt->execute();
    $stmt->close();

    if ($assignments) {
        $stmt = $conn->prepare('INSERT INTO class_teacher (class_id, teacher_id, assigned_role) VALUES (?, ?, ?)');
        foreach ($assignments as $a) {
            $stmt->bind_param('iis', $class_id, $a['teacher_id'], $a['role']);
            $stmt->execute();
        }
        $stmt->close();
    }
}
