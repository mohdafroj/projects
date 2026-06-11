from jose import jwt, jwe
import json
from datetime import datetime, timezone, timedelta

# Create JWS
secret = "882e566736203115c544837549b67484d85202860d5b630f784f1837d3897850"
encryption_key = secret[:32].encode() # 32 bytes for JWE

expire = datetime.now(timezone.utc) + timedelta(minutes=15)
to_encode = {"exp": expire, "sub": "123", "type": "access"}
signed_token = jwt.encode(to_encode, secret, algorithm="HS256")
print("Signed:", signed_token)

# Encrypt the signed token
encrypted_token = jwe.encrypt(signed_token.encode(), encryption_key, algorithm='dir', encryption='A256GCM').decode()
print("Encrypted:", encrypted_token)

# Decrypt the token
decrypted_token = jwe.decrypt(encrypted_token.encode(), encryption_key).decode()
print("Decrypted matches Signed:", decrypted_token == signed_token)

# Verify JWT
payload = jwt.decode(decrypted_token, secret, algorithms=["HS256"])
print("Payload:", payload)
