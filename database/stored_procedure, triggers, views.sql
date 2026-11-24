-- Stored Procedures and Triggers

-- 1. sp_create_class
DROP PROCEDURE IF EXISTS `sp_create_class`;
DELIMITER $$
CREATE PROCEDURE `sp_create_class` (
    IN p_created_by INT,
    IN p_class_title VARCHAR(200),
    IN p_description TEXT,
    IN p_max_capacity INT,
    IN p_teachers_json JSON,
    IN p_status ENUM('Draft', 'Open', 'Ongoing', 'Completed', 'Cancelled'),
    IN p_session_date DATE,
    IN p_start_time TIME,
    IN p_end_time TIME,
    IN p_venue VARCHAR(255)
)
BEGIN
    DECLARE v_class_id INT;
    DECLARE v_idx INT DEFAULT 0;
    DECLARE v_count INT DEFAULT 0;
    DECLARE v_teacher_id INT;
    DECLARE v_role VARCHAR(50);

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    START TRANSACTION;

    -- Insert into class table
    INSERT INTO `class` (`created_by`, `class_title`, `description`, `max_capacity`, `current_enrolled`, `status`)
    VALUES (p_created_by, p_class_title, p_description, p_max_capacity, 0, IFNULL(p_status, 'Open'));

    SET v_class_id = LAST_INSERT_ID();

    -- Loop through teachers in the JSON array
    SET v_count = IFNULL(JSON_LENGTH(p_teachers_json), 0);
    WHILE v_idx < v_count DO
        SET v_teacher_id = JSON_UNQUOTE(JSON_EXTRACT(p_teachers_json, CONCAT('$[', v_idx, '].teacher_id')));
        SET v_role = JSON_UNQUOTE(JSON_EXTRACT(p_teachers_json, CONCAT('$[', v_idx, '].role')));

        INSERT INTO `class_teacher` (`class_id`, `teacher_id`, `assigned_role`)
        VALUES (v_class_id, v_teacher_id, v_role);

        SET v_idx = v_idx + 1;
    END WHILE;

    -- Insert first session into class_schedule table
    INSERT INTO `class_schedule` (`class_id`, `session_date`, `start_time`, `end_time`, `venue`, `status`)
    VALUES (v_class_id, p_session_date, p_start_time, p_end_time, p_venue, 'scheduled');

    COMMIT;
END$$
DELIMITER ;

-- 2. sp_enroll_learner
DROP PROCEDURE IF EXISTS `sp_enroll_learner`;
DELIMITER $$
CREATE PROCEDURE `sp_enroll_learner` (
    IN p_class_id INT,
    IN p_learner_id INT
)
BEGIN
    DECLARE v_status VARCHAR(20);
    DECLARE v_enrolled INT;
    DECLARE v_capacity INT;

    SELECT `status`, `max_capacity`, `current_enrolled`
    INTO v_status, v_capacity, v_enrolled
    FROM `class`
    WHERE `class_id` = p_class_id
    FOR UPDATE;

    IF v_status IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Class does not exist.';
    ELSEIF v_status <> 'Open' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Class is not open for enrollment.';
    ELSEIF v_enrolled >= v_capacity THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Class has reached maximum capacity.';
    ELSE
        INSERT INTO `class_enrollments` (`class_id`, `learner_id`, `status`)
        VALUES (p_class_id, p_learner_id, 'enrolled');
    END IF;
END$$
DELIMITER ;
-- 3. Enrollment Count Sync Triggers
DROP TRIGGER IF EXISTS `trg_enrollment_after_insert`;
DELIMITER $$
CREATE TRIGGER `trg_enrollment_after_insert`
AFTER INSERT ON `class_enrollments`
FOR EACH ROW
BEGIN
    IF NEW.`status` = 'enrolled' THEN
        UPDATE `class`
        SET `current_enrolled` = `current_enrolled` + 1
        WHERE `class_id` = NEW.`class_id`;
    END IF;
END$$
DELIMITER ;

DROP TRIGGER IF EXISTS `trg_enrollment_after_update`;
DELIMITER $$
CREATE TRIGGER `trg_enrollment_after_update`
AFTER UPDATE ON `class_enrollments`
FOR EACH ROW
BEGIN
    IF OLD.`status` <> 'enrolled' AND NEW.`status` = 'enrolled' THEN
        UPDATE `class`
        SET `current_enrolled` = `current_enrolled` + 1
        WHERE `class_id` = NEW.`class_id`;
    ELSEIF OLD.`status` = 'enrolled' AND NEW.`status` <> 'enrolled' THEN
        UPDATE `class`
        SET `current_enrolled` = GREATEST(`current_enrolled` - 1, 0)
        WHERE `class_id` = NEW.`class_id`;
    END IF;
END$$
DELIMITER ;

DROP TRIGGER IF EXISTS `trg_enrollment_after_delete`;
DROP TRIGGER IF EXISTS `trg_enrollement_after_delete`;
DELIMITER $$
CREATE TRIGGER `trg_enrollment_after_delete`
AFTER DELETE ON `class_enrollments`
FOR EACH ROW
BEGIN
    IF OLD.`status` = 'enrolled' THEN
        UPDATE `class`
        SET `current_enrolled` = GREATEST(`current_enrolled` - 1, 0)
        WHERE `class_id` = OLD.`class_id`;
    END IF;
END$$
DELIMITER ;





-- initializing and retrieving post-class report

-- trigger to create a draft report when the class-schedule is ongoing for attendance

-- 4. Auto-create Pending Post-Class Report when Session Starts
DROP TRIGGER IF EXISTS `trg_schedule_start_draft_report`;
DELIMITER $$
CREATE TRIGGER `trg_schedule_start_draft_report`
AFTER UPDATE ON `class_schedule`
FOR EACH ROW
BEGIN
    DECLARE v_report_exists INT DEFAULT 0;

    IF OLD.`status` <> 'ongoing' AND NEW.`status` = 'ongoing' THEN
        SELECT COUNT(*) INTO v_report_exists
        FROM `post_class_report`
        WHERE `schedule_id` = NEW.`schedule_id`;

        IF v_report_exists = 0 THEN
            INSERT INTO `post_class_report` (`schedule_id`, `status`)
            VALUES (NEW.`schedule_id`, 'Pending');
        END IF;
    END IF;
END$$
DELIMITER ;

-- take the attendance during class after auto creating the report

-- 5. sp_record_attendance
DROP PROCEDURE IF EXISTS `sp_record_attendance`;
DELIMITER $$
CREATE PROCEDURE `sp_record_attendance` (
    IN p_report_id INT,
    IN p_learner_id INT,
    IN p_status ENUM('present', 'absent', 'late', 'excused'),
    IN p_remarks VARCHAR(255)
)
BEGIN
    DECLARE v_report_id INT DEFAULT NULL;
    DECLARE v_status ENUM('Pending', 'Finished') DEFAULT NULL;

    SELECT `report_id`, `status`
    INTO v_report_id, v_status
    FROM `post_class_report`
    WHERE `report_id` = p_report_id
    FOR UPDATE;

    IF v_report_id IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Post-class report does not exist.';
    ELSEIF v_status = 'Pending' THEN
        INSERT INTO `session_attendance` (`report_id`, `learner_id`, `status`, `remarks`)
        VALUES (p_report_id, p_learner_id, p_status, p_remarks)
        ON DUPLICATE KEY UPDATE
            `status` = VALUES(`status`),
            `remarks` = VALUES(`remarks`);
    ELSE
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'The report is already finished.';
    END IF;
END$$
DELIMITER ;




-- 6. sp_grade_learner_session
DROP PROCEDURE IF EXISTS `sp_grade_learner_session`;
DELIMITER $$
CREATE PROCEDURE `sp_grade_learner_session` (
    IN p_report_id INT,
    IN p_learner_id INT,
    IN p_session_rating ENUM('Satisfactory', 'Needs Practice', 'Unsatisfactory'),
    IN p_remarks VARCHAR(255)
)
BEGIN
    DECLARE v_report_status ENUM('Pending', 'Finished') DEFAULT NULL;
    DECLARE v_learner_id INT DEFAULT NULL;
    DECLARE v_status ENUM('present', 'absent', 'late', 'excused') DEFAULT NULL;

    SELECT `status`
    INTO v_report_status
    FROM `post_class_report`
    WHERE `report_id` = p_report_id
    FOR UPDATE;

    IF v_report_status IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Post-class report does not exist.';
    ELSEIF v_report_status <> 'Pending' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Cannot grade learners on a finished report.';
    END IF;

    SELECT `learner_id`, `status`
    INTO v_learner_id, v_status
    FROM `session_attendance`
    WHERE `learner_id` = p_learner_id AND `report_id` = p_report_id
    FOR UPDATE;

    IF v_learner_id IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Learner has no attendance for this session.';
    ELSEIF v_status NOT IN ('present', 'late') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Absent learners cannot be graded.';
    ELSE
        UPDATE `session_attendance`
        SET `session_rating` = p_session_rating,
            `remarks` = COALESCE(NULLIF(TRIM(p_remarks), ''), `remarks`)
        WHERE `report_id` = p_report_id
          AND `learner_id` = p_learner_id;
    END IF;
END$$
DELIMITER ;







-- 7. sp_finalize_class_report
DROP PROCEDURE IF EXISTS `sp_finalize_class_report`;
DELIMITER $$
CREATE PROCEDURE `sp_finalize_class_report` (
    IN p_report_id INT,
    IN p_teacher_id INT,
    IN p_summary_notes TEXT
)
BEGIN
    DECLARE v_schedule_id INT DEFAULT NULL;
    DECLARE v_status ENUM('Pending', 'Finished') DEFAULT NULL;

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    START TRANSACTION;

    SELECT `schedule_id`, `status`
    INTO v_schedule_id, v_status
    FROM `post_class_report`
    WHERE `report_id` = p_report_id
    FOR UPDATE;

    IF v_schedule_id IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Post-class report does not exist.';
    ELSEIF v_status <> 'Pending' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'This report has already been finalized.';
    END IF;

    UPDATE `post_class_report`
    SET `submitted_by` = p_teacher_id,
        `summary_notes` = p_summary_notes,
        `status` = 'Finished'
    WHERE `report_id` = p_report_id;

    UPDATE `class_schedule`
    SET `status` = 'completed'
    WHERE `schedule_id` = v_schedule_id;

    COMMIT;
END$$
DELIMITER ;
-- view for attendance summary in report

CREATE OR REPLACE VIEW `vw_session_attendance_summary` AS
SELECT 
    cs.schedule_id,
    c.class_id,
    c.class_title,
    cs.session_date,
    cs.start_time,
    cs.end_time,
    cs.venue,
    cs.status AS session_status,
    COUNT(sa.attendance_id) AS total_recorded,
    SUM(CASE WHEN sa.status = 'present' THEN 1 ELSE 0 END) AS present_count,
    SUM(CASE WHEN sa.status = 'absent' THEN 1 ELSE 0 END) AS absent_count,
    SUM(CASE WHEN sa.status = 'excused' THEN 1 ELSE 0 END) AS excused_count,
    SUM(CASE WHEN sa.status = 'late' THEN 1 ELSE 0 END) AS late_count,
    ROUND(
        (SUM(CASE WHEN sa.status = 'present' THEN 1 ELSE 0 END) / NULLIF(COUNT(sa.attendance_id), 0)) * 100,
        2
    ) AS attendance_rate_pct,
    pcr.report_id,
    CONCAT(t.first_name, ' ', t.last_name) AS submitted_by_teacher,
    pcr.submission_timestamp
FROM `class_schedule` cs
JOIN `class` c ON c.class_id = cs.class_id
LEFT JOIN `post_class_report` pcr ON pcr.schedule_id = cs.schedule_id
LEFT JOIN `session_attendance` sa ON sa.report_id = pcr.report_id
LEFT JOIN `teachers` t ON t.teacher_id = pcr.submitted_by
GROUP BY 
    cs.schedule_id, c.class_id, c.class_title, cs.session_date, 
    cs.start_time, cs.end_time, cs.venue, cs.status, 
    pcr.report_id, t.first_name, t.last_name, pcr.submission_timestamp;
-- create view to view assigned class schedules to a teacher

CREATE OR REPLACE VIEW `vw_teacher_assigned_schedules` AS
SELECT 
    ct.teacher_id,
    CONCAT(t.first_name, ' ', t.last_name) AS teacher_name,
    ct.assigned_role,
    c.class_id,
    c.class_title,
    c.description,
    c.status AS class_status,
    c.current_enrolled,
    c.max_capacity,
    cs.schedule_id,
    cs.session_date,
    cs.start_time,
    cs.end_time,
    cs.venue,
    cs.status AS session_status
FROM `class_teacher` ct
JOIN `teachers` t ON t.teacher_id = ct.teacher_id
JOIN `class` c ON c.class_id = ct.class_id
JOIN `class_schedule` cs ON cs.class_id = c.class_id;

-- create view to view enrolled rosters and profiles for teacher and admin

CREATE OR REPLACE VIEW `vw_class_enrolled_rosters` AS
SELECT 
  ce.class_id,
  c.class_title,
  l.learner_id,
  CONCAT(l.first_name, ' ', l.last_name) AS full_name,
  l.hearing_status,
  l.speech_status,
  l.contact_number,
  a.email,
  b.barangay_name,
  m.municipality_name,
  ce.enrollment_date
FROM `class_enrollments` ce
JOIN `class` c ON c.class_id = ce.class_id
JOIN `learners` l ON l.learner_id = ce.learner_id
JOIN `accounts` a ON a.account_id = l.account_id
LEFT JOIN `barangays` b ON b.barangay_id = l.barangay_id
LEFT JOIN `municipalities` m ON m.municipality_id = b.municipality_id;
-- create view to view the attendance and grades as learner and teacher

CREATE OR REPLACE VIEW `vw_session_attendance_grades` AS
SELECT 
    sa.attendance_id,
    sa.learner_id,
    CONCAT(l.last_name, ', ', l.first_name) AS learner_name,
    c.class_id,
    c.class_title,
    cs.schedule_id,
    cs.session_date,
    cs.start_time,
    cs.end_time,
    cs.venue,
    sa.status AS attendance_status,
    sa.session_rating,
    sa.remarks,
    pcr.report_id
FROM `session_attendance` sa
JOIN `learners` l ON l.learner_id = sa.learner_id
JOIN `post_class_report` pcr ON pcr.report_id = sa.report_id
JOIN `class_schedule` cs ON cs.schedule_id = pcr.schedule_id
JOIN `class` c ON c.class_id = cs.class_id;