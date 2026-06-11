import random
import string
import io
import uuid
from captcha.image import ImageCaptcha

class CaptchaTool:
    def __init__(self, width=160, height=60):
        self.image = ImageCaptcha(width=width, height=height)

    def generate_random_text(self, length=4):
        # Use only numbers and letters that are easy to distinguish
        chars = string.ascii_uppercase + string.digits
        # Remove ambiguous characters
        chars = chars.replace('0', '').replace('O', '').replace('I', '').replace('1', '')
        return ''.join(random.choices(chars, k=length))

    def create_captcha_image(self, text):
        data = self.image.generate(text)
        return data.getvalue()

captcha_tool = CaptchaTool()
