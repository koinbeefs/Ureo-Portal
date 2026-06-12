@echo off
echo Adding body_html column to email_logs table...
mysql -u root -p ureo_portal < database\migrations\add_body_html_column.sql
echo Migration complete!
pause
