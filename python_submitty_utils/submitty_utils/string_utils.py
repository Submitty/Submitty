import random
import string


def generate_random_string(length):
    """
    Return an uppercase string of n letters.

    :return:
    :rtype: string
    """
    choices = string.ascii_letters + string.digits
    return ''.join(random.choices(choices, k=length))
