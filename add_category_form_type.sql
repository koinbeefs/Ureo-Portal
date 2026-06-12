-- Add category_form to fillable_forms table
ALTER TABLE fillable_forms MODIFY COLUMN form_type ENUM('qf01', 'qf02', 'category_form') NOT NULL;
