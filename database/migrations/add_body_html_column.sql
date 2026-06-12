-- Migration: Add body_html column to email_logs table
-- Date: February 2, 2026
-- Description: Add missing body_html column to store email content

ALTER TABLE email_logs ADD COLUMN body_html TEXT AFTER subject;
