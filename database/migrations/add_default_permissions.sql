-- Migration: Add default permissions for sidebar modules
-- Run this if modules are not displaying in the sidebar
--
-- This script adds view permissions for all existing roles
-- for the modules displayed in the sidebar

-- First, let's see what roles exist
-- SELECT id, role_name FROM roles WHERE is_active = 1;

-- Insert default permissions for all active roles
-- Modules: company, employees, attendance, users, settings

-- For role_id 2 (typically HR Manager or similar)
INSERT INTO role_permissions (role_id, module, can_view, can_create, can_edit, can_delete, can_approve, can_export, is_deleted, created_at)
SELECT 2, 'company', 1, 1, 1, 0, 0, 1, 0, NOW()
WHERE NOT EXISTS (SELECT 1 FROM role_permissions WHERE role_id = 2 AND module = 'company' AND is_deleted = 0);

INSERT INTO role_permissions (role_id, module, can_view, can_create, can_edit, can_delete, can_approve, can_export, is_deleted, created_at)
SELECT 2, 'employees', 1, 1, 1, 0, 0, 1, 0, NOW()
WHERE NOT EXISTS (SELECT 1 FROM role_permissions WHERE role_id = 2 AND module = 'employees' AND is_deleted = 0);

INSERT INTO role_permissions (role_id, module, can_view, can_create, can_edit, can_delete, can_approve, can_export, is_deleted, created_at)
SELECT 2, 'attendance', 1, 1, 1, 0, 1, 1, 0, NOW()
WHERE NOT EXISTS (SELECT 1 FROM role_permissions WHERE role_id = 2 AND module = 'attendance' AND is_deleted = 0);

INSERT INTO role_permissions (role_id, module, can_view, can_create, can_edit, can_delete, can_approve, can_export, is_deleted, created_at)
SELECT 2, 'users', 1, 1, 1, 0, 0, 0, 0, NOW()
WHERE NOT EXISTS (SELECT 1 FROM role_permissions WHERE role_id = 2 AND module = 'users' AND is_deleted = 0);

INSERT INTO role_permissions (role_id, module, can_view, can_create, can_edit, can_delete, can_approve, can_export, is_deleted, created_at)
SELECT 2, 'settings', 1, 0, 1, 0, 0, 0, 0, NOW()
WHERE NOT EXISTS (SELECT 1 FROM role_permissions WHERE role_id = 2 AND module = 'settings' AND is_deleted = 0);

-- For role_id 3 (typically Employee or similar - view only)
INSERT INTO role_permissions (role_id, module, can_view, can_create, can_edit, can_delete, can_approve, can_export, is_deleted, created_at)
SELECT 3, 'employees', 1, 0, 0, 0, 0, 0, 0, NOW()
WHERE NOT EXISTS (SELECT 1 FROM role_permissions WHERE role_id = 3 AND module = 'employees' AND is_deleted = 0);

INSERT INTO role_permissions (role_id, module, can_view, can_create, can_edit, can_delete, can_approve, can_export, is_deleted, created_at)
SELECT 3, 'attendance', 1, 1, 0, 0, 0, 0, 0, NOW()
WHERE NOT EXISTS (SELECT 1 FROM role_permissions WHERE role_id = 3 AND module = 'attendance' AND is_deleted = 0);

-- Quick alternative: Insert permissions for ALL existing roles at once
-- Uncomment and run if needed:
/*
INSERT INTO role_permissions (role_id, module, can_view, can_create, can_edit, can_delete, can_approve, can_export, is_deleted, created_at)
SELECT r.id, m.module, 1, 0, 0, 0, 0, 0, 0, NOW()
FROM roles r
CROSS JOIN (
    SELECT 'company' AS module
    UNION SELECT 'employees'
    UNION SELECT 'attendance'
    UNION SELECT 'users'
    UNION SELECT 'settings'
) m
WHERE r.is_active = 1
AND r.id != 1  -- Skip super admin (already has all permissions)
AND NOT EXISTS (
    SELECT 1 FROM role_permissions rp
    WHERE rp.role_id = r.id
    AND rp.module = m.module
    AND rp.is_deleted = 0
);
*/

-- Verify the permissions were added
-- SELECT rp.id, r.role_name, rp.module, rp.can_view FROM role_permissions rp JOIN roles r ON rp.role_id = r.id WHERE rp.is_deleted = 0 ORDER BY r.id, rp.module;
