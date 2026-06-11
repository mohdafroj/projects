import logging
import re
import sys

SENSITIVE_KEYS = [
    "password", 
    "access_token", 
    "refresh_token", 
    "token", 
    "hashed_password", 
    "secret", 
    "client_secret",
    "captcha_code"
]

class SensitiveDataFormatter(logging.Formatter):
    """
    Custom formatter that redacts sensitive information from log messages.
    It looks for key-value pairs (JSON, dicts, kwargs) and masks the values.
    """
    def __init__(self, fmt=None, datefmt=None, style='%'):
        super().__init__(fmt, datefmt, style)
        # Regex to match: 'key': 'value', "key": "value", or key=value
        keys_pattern = "|".join(SENSITIVE_KEYS)
        self.pattern = re.compile(
            rf'([\'"]?)({keys_pattern})\1\s*[:=]\s*([\'"]?)[^\'",\s}}]+([\'"]?)',
            re.IGNORECASE
        )

    def format(self, record: logging.LogRecord) -> str:
        # Get the original formatted message
        original_msg = super().format(record)
        # Redact the sensitive values
        redacted_msg = self.pattern.sub(r'\1\2\1: \3***MASKED***\4', original_msg)
        return redacted_msg

def setup_logging():
    """
    Configures the root logger and uvicorn loggers to use the sensitive data formatter.
    """
    log_format = "%(asctime)s - %(name)s - %(levelname)s - %(message)s"
    formatter = SensitiveDataFormatter(fmt=log_format)

    # Configure root logger
    root_logger = logging.getLogger()
    root_logger.setLevel(logging.INFO)
    
    # We want to clear existing handlers to avoid duplicate logs, 
    # but Uvicorn sets its own handlers. We will overwrite their formatters.
    
    console_handler = logging.StreamHandler(sys.stdout)
    console_handler.setFormatter(formatter)
    
    # Replace handlers on the root logger
    if root_logger.hasHandlers():
        root_logger.handlers.clear()
    root_logger.addHandler(console_handler)

    # Apply to Uvicorn loggers specifically
    for logger_name in ("uvicorn", "uvicorn.access", "uvicorn.error"):
        logger = logging.getLogger(logger_name)
        for handler in logger.handlers:
            handler.setFormatter(formatter)
