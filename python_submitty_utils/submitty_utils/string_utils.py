import random
import string


def generate_random_string(length):
    """
    Return an uppercase string of n letters.

    :return:
    :rtype: string
    """
    return ''.join(random.choice(string.ascii_letters + string.digits) for i in range(length))
