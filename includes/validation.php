<?php
/**
 * Input Validation Helper
 * Comprehensive input validation and sanitization
 */

require_once __DIR__ . '/db.php';

/**
 * Validate and sanitize string input
 */
function validateString($input, $maxLength = null, $required = false) {
    if ($required && empty(trim($input))) {
        throw new Exception('This field is required');
    }

    if (empty($input)) {
        return '';
    }

    $sanitized = trim($input);

    if ($maxLength && strlen($sanitized) > $maxLength) {
        throw new Exception("Input exceeds maximum length of {$maxLength} characters");
    }

    // Remove potentially dangerous characters
    $sanitized = filter_var($sanitized, FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);

    return $sanitized;
}

/**
 * Validate email address
 */
function validateEmail($email, $required = false) {
    if ($required && empty(trim($email))) {
        throw new Exception('Email is required');
    }

    if (empty($email)) {
        return '';
    }

    $email = trim($email);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email format');
    }

    return $email;
}

/**
 * Validate integer with range
 */
function validateInt($input, $min = null, $max = null, $required = false) {
    if ($required && $input === null) {
        throw new Exception('This field is required');
    }

    if ($input === null || $input === '') {
        return null;
    }

    if (!is_numeric($input)) {
        throw new Exception('Must be a valid number');
    }

    $intValue = (int)$input;

    if ($min !== null && $intValue < $min) {
        throw new Exception("Value must be at least {$min}");
    }

    if ($max !== null && $intValue > $max) {
        throw new Exception("Value must be at most {$max}");
    }

    return $intValue;
}

/**
 * Validate enum value
 */
function validateEnum($input, $allowedValues, $required = false) {
    if ($required && empty($input)) {
        throw new Exception('This field is required');
    }

    if (empty($input)) {
        return null;
    }

    if (!in_array($input, $allowedValues)) {
        throw new Exception('Invalid value selected');
    }

    return $input;
}

/**
 * Validate date format
 */
function validateDate($date, $required = false) {
    if ($required && empty(trim($date))) {
        throw new Exception('Date is required');
    }

    if (empty($date)) {
        return null;
    }

    $dateObj = DateTime::createFromFormat('Y-m-d', $date);
    if (!$dateObj || $dateObj->format('Y-m-d') !== $date) {
        throw new Exception('Invalid date format (expected YYYY-MM-DD)');
    }

    return $date;
}

/**
 * Validate file upload
 */
function validateFileUpload($file, $allowedTypes = [], $maxSize = null) {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('File upload failed');
    }

    // Check file size
    if ($maxSize && $file['size'] > $maxSize) {
        throw new Exception('File size exceeds limit');
    }

    // Check file type
    if (!empty($allowedTypes)) {
        $fileType = mime_content_type($file['tmp_name']);
        if (!in_array($fileType, $allowedTypes)) {
            throw new Exception('File type not allowed');
        }
    }

    // Check for malicious content
    if ($file['size'] > 0) {
        $content = file_get_contents($file['tmp_name']);
        if (preg_match('/<\?php|<\?|\b(eval|exec|system|shell_exec|passthru)\b/i', $content)) {
            throw new Exception('File contains potentially malicious content');
        }
    }

    return $file;
}

/**
 * Validate ULID format
 */
function validateULID($ulid, $required = false) {
    if ($required && empty($ulid)) {
        throw new Exception('ID is required');
    }

    if (empty($ulid)) {
        return null;
    }

    // ULID format: 26 characters, base32
    if (!preg_match('/^[0123456789ABCDEFGHJKMNPQRSTVWXYZ]{26}$/', $ulid)) {
        throw new Exception('Invalid ID format');
    }

    return $ulid;
}

/**
 * Sanitize HTML content (basic)
 */
function sanitizeHtml($html) {
    if (empty($html)) {
        return '';
    }

    // Remove script tags and other dangerous elements
    $html = preg_replace('/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi', '', $html);
    $html = preg_replace('/<iframe\b[^<]*(?:(?!<\/iframe>)<[^<]*)*<\/iframe>/gi', '', $html);

    // Allow only safe tags
    $allowedTags = '<p><br><strong><em><u><h1><h2><h3><h4><h5><h6><ul><ol><li><a>';
    $html = strip_tags($html, $allowedTags);

    return trim($html);
}

/**
 * Validate URL
 */
function validateUrl($url, $required = false) {
    if ($required && empty(trim($url))) {
        throw new Exception('URL is required');
    }

    if (empty($url)) {
        return '';
    }

    $url = trim($url);

    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        throw new Exception('Invalid URL format');
    }

    return $url;
}

/**
 * Validate phone number (basic)
 */
function validatePhone($phone, $required = false) {
    if ($required && empty(trim($phone))) {
        throw new Exception('Phone number is required');
    }

    if (empty($phone)) {
        return '';
    }

    $phone = trim($phone);

    // Remove all non-digit characters
    $digitsOnly = preg_replace('/\D/', '', $phone);

    // Check if it's a reasonable length
    if (strlen($digitsOnly) < 7 || strlen($digitsOnly) > 15) {
        throw new Exception('Invalid phone number format');
    }

    return $phone;
}