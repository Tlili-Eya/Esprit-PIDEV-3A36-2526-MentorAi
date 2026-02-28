"""
Face++ Login Microservice
Run: python app.py
Port: 5001
"""
import os
import requests
from flask import Flask, request, jsonify
from dotenv import load_dotenv

load_dotenv()

app = Flask(__name__)

FACEPP_API_KEY    = os.getenv('FACEPP_API_KEY', '')
FACEPP_API_SECRET = os.getenv('FACEPP_API_SECRET', '')
FACEPP_COMPARE_URL = 'https://api-us.faceplusplus.com/facepp/v3/compare'
THRESHOLD = 70.0


@app.route('/health', methods=['GET'])
def health():
    return jsonify({'status': 'ok', 'service': 'face-login'})


@app.route('/api/face-login', methods=['POST'])
def face_login():
    data = request.get_json(silent=True)

    if not data:
        return jsonify({'success': False, 'message': 'No JSON data received'}), 400

    captured_image = data.get('captured_image')
    users          = data.get('users', [])

    if not captured_image:
        return jsonify({'success': False, 'message': 'captured_image is required'}), 400

    if not users:
        return jsonify({'success': False, 'message': 'No users to compare against'}), 400

    if not FACEPP_API_KEY or not FACEPP_API_SECRET:
        return jsonify({'success': False, 'message': 'Face++ API credentials not configured'}), 500

    best_match      = None
    best_confidence = 0.0

    for user in users:
        user_id      = user.get('user_id')
        photo_base64 = user.get('photo_base64')
        role         = user.get('role')

        if not photo_base64:
            continue

        try:
            resp = requests.post(
                FACEPP_COMPARE_URL,
                data={
                    'api_key':        FACEPP_API_KEY,
                    'api_secret':     FACEPP_API_SECRET,
                    'image_base64_1': captured_image,
                    'image_base64_2': photo_base64,
                },
                timeout=10,
            )
            result = resp.json()

            # Face++ returns error_message if no face detected
            if 'error_message' in result:
                continue

            confidence = float(result.get('confidence', 0.0))

            if confidence >= THRESHOLD and confidence > best_confidence:
                best_confidence = confidence
                best_match = {'user_id': user_id, 'role': role}

        except Exception as exc:
            print(f'[face-login] Error comparing user {user_id}: {exc}')
            continue

    if best_match:
        return jsonify({
            'success':    True,
            'user_id':    best_match['user_id'],
            'role':       best_match['role'],
            'confidence': round(best_confidence, 2),
        })

    return jsonify({'success': False, 'message': 'Visage non reconnu. Veuillez réessayer.'})


if __name__ == '__main__':
    print('Face++ Login Service running on http://127.0.0.1:5001')
    app.run(host='127.0.0.1', port=5001, debug=False)
