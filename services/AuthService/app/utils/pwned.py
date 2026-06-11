import hashlib
import httpx

async def check_pwned_password(password: str) -> int:
    """
    Checks if a password has been exposed in data breaches using HaveIBeenPwned.
    Uses k-Anonymity model (only sends first 5 characters of SHA-1 hash).
    Returns the number of times the password was found (0 if safe).
    """
    # 1. Hash the password with SHA-1
    sha1_password = hashlib.sha1(password.encode('utf-8')).hexdigest().upper()
    prefix = sha1_password[:5]
    suffix = sha1_password[5:]

    # 2. Query HIBP API with only the 5-character prefix
    url = f"https://api.pwnedpasswords.com/range/{prefix}"
    
    try:
        async with httpx.AsyncClient(timeout=5.0) as client:
            response = await client.get(url)
            
        if response.status_code != 200:
            # If the API is down, fail open (allow the password) so we don't break login
            return 0
            
        # 3. Search the response for our specific suffix
        hashes = (line.split(':') for line in response.text.splitlines())
        for h, count in hashes:
            if h == suffix:
                return int(count)
                
    except Exception as e:
        # Fail open on network errors
        print(f"Error checking pwned passwords: {e}")
        pass
        
    return 0
