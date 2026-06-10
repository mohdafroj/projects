from jose import jwe
import json

secret = "09d25e094faa6ca2556c818166b7a9563b93f7099f6f0f4caa6cf63b88e8d3e7"[:32].encode() # 32 bytes for A256GCM
payload = json.dumps({"sub": "123", "type": "access"}).encode()

try:
    encrypted = jwe.encrypt(payload, secret, algorithm='dir', encryption='A256GCM')
    print("Encrypted:", encrypted.decode())
    decrypted = jwe.decrypt(encrypted, secret)
    print("Decrypted:", decrypted.decode())
except Exception as e:
    print("Error:", e)
