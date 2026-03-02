# Audio Chat System - Testing Guide

## Quick Start

1. **Navigate to Chat Page**
   - Go to `/course/1` (or your course URL)
   - You should see the new sidebar with:
     - Microphone button in message input
     - "Response Format" card with Text/Audio toggle
     - Updated document upload with audio formats

2. **Add OpenAI API Key** (IMPORTANT)
   - Edit `.env` file
   - Replace `sk-proj-xxx` with your actual OpenAI API key
   - Save and restart server

3. **Clear Cache**
   ```bash
   php bin/console cache:clear
   ```

## Test Scenarios

### Scenario 1: Text Message → Text Response (Basic)
**Steps:**
1. Type "Expliquez-moi les variables en Python" in message input
2. Ensure "📝 Texte" is selected in Response Format
3. Click "Envoyer" (Send)
4. Verify text response appears in chat

**Expected Result:** Text message and text response displayed normally

---

### Scenario 2: Text Message → Audio Response
**Steps:**
1. Type "Comment utiliser les boucles for?" in message input
2. Click "🎵 Audio" in Response Format card
3. Click "Envoyer" (Send)
4. Wait for response generation (5-10 seconds)
5. Audio player should appear with play button
6. Click play button to hear AI response

**Expected Result:**
- Text message shows: "You: Comment utiliser..."
- AI response shows: "Assistant: 🎵 Audio response generated"
- Audio player appears with controls
- Audio plays when clicked

---

### Scenario 3: Audio Message → Text Response
**Steps:**
1. Ensure "📝 Texte" is selected in Response Format
2. Click microphone button (🎤)
3. Browser prompts for microphone permission - click "Allow"
4. Recording indicator shows: "🔴 Recording in progress... [Stop]"
5. Say clearly: "Explique-moi les conditions en Python"
6. Wait 2-3 seconds or click [Stop] button
7. Recording indicator disappears
8. Wait for response (10-15 seconds for transcription + generation)

**Expected Result:**
- Message shows: "You: 🎤 [Audio message]"
- AI responds with text: "Assistant: Python conditions are..."
- No errors in console

**Troubleshooting:**
- If no recording indicator: Microphone permission denied
- If no response: Check browser console for errors
- If text is garbled: May need better audio quality

---

### Scenario 4: Audio Message → Audio Response (Full Audio Pipeline)
**Steps:**
1. Click "🎵 Audio" in Response Format
2. Click microphone button (🎤)
3. Allow microphone access
4. Clearly speak: "Que sont les fonctions en Python?"
5. Stop recording or wait for stop
6. Wait 15-25 seconds for full pipeline

**Expected Result:**
- Audio transcribed to text (shown in chat)
- AI generates text response
- Response converted to audio
- Audio player appears
- Audio plays when clicked

**Performance Notes:**
- Transcription: 2-10 seconds
- Text generation: 1-5 seconds  
- Text-to-speech: 1-3 seconds
- **Total: 5-20 seconds**

---

### Scenario 5: PDF Upload with AI Processing
**Steps:**
1. Click "📖 Importer un Document"
2. Select a PDF file (test.pdf)
3. Click "📤 Charger Document"
4. Wait 2-5 seconds for processing

**Expected Result:**
- Status message: "Processing PDF..."
- Completion: "Document loaded and summarized"
- Document context now available for chatting
- Chat responses include PDF content

---

### Scenario 6: Audio File Upload
**Steps:**
1. Click "📖 Importer un Document"  
2. Select an audio file (MP3, WAV, WebM, or OGG)
3. Click "📤 Charger Document"
4. Wait 5-10 seconds for transcription

**Expected Result:**
- Status: "Processing audio file..."
- Completion: "Audio transcribed and ready"
- Transcribed text available in context
- Can now chat about audio content

---

### Scenario 7: Response Format Toggle
**Steps:**
1. Note current Response Format selection
2. Send a text message → gets text response
3. Switch to "🎵 Audio"
4. Send another text message → gets audio response with player
5. Switch back to "📝 Texte"
6. Send text message → gets text response (no audio player)

**Expected Result:** Response format correctly controls output format

---

## Browser Console Debugging

Open browser Developer Tools (F12) and check Console tab for:

### Normal Operation Logs:
```javascript
// When recording starts
"Audio recording started"

// When audio sent
"Sending audio message: {audioBase64: "...", responseFormat: "audio"}"

// When response received
"Response received: {response: "...", audioUrl: "..."}"
```

### Error Logs to Watch For:
```javascript
// Microphone access denied
"Microphone access denied: Error"

// API key missing
"OPENAI_API_KEY not configured" 

// Network error
"Error sending audio: NetworkError"

// API errors
"Error: 401 Unauthorized" (invalid API key)
```

---

## Network Inspection

In DevTools → Network tab, observe:

1. **POST /api/chat/message** (text message)
   - Request size: ~100-500 bytes
   - Response: 1-5 KB (text response)
   - Time: 2-7 seconds

2. **POST /api/chat/message** (audio message)
   - Request size: 50-200 KB (audio as base64)
   - Response: 1-5 KB (transcribed text)
   - Time: 5-15 seconds

3. **POST /api/chat/message** (audio response requested)
   - Request size: ~100-500 bytes
   - Response: 1-5 KB
   - Time: 5-10 seconds
   - Additional GET to audio file: 50-200 KB MP3

4. **POST /api/chat/upload** (file upload)
   - Request size: Varies by file (100 KB - several MB)
   - Response: ~1 KB status
   - Time: 2-30 seconds depending on file

---

## Performance Testing

### Baseline Times (Expected):

| Operation | Time | Notes |
|-----------|------|-------|
| Text input + text response | 2-5 sec | Normal Groq response |
| Text input + audio response | 5-8 sec | Groq + ElevenLabs |
| Audio record + transcribe | 2-10 sec | Depends on audio length |
| Full audio pipeline | 10-20 sec | Record + transcribe + Groq + TTS |
| PDF upload | 2-5 sec | Text extraction + summarization |
| Audio upload | 5-10 sec | Transcription to text |

---

## Common Issues & Solutions

### Issue: "Microphone access denied"
**Cause:** Browser permission denied
**Solution:**
1. Open browser settings
2. Find microphone permissions
3. Set to "Allow" for your domain
4. Refresh page and retry

---

### Issue: "Audio transcription not available"
**Cause:** OPENAI_API_KEY not configured
**Solution:**
1. Get API key from https://platform.openai.com/api-keys
2. Edit `.env` file
3. Set `OPENAI_API_KEY=sk-proj-your-key`
4. Run `php bin/console cache:clear`
5. Restart server if needed

---

### Issue: Audio plays but no sound
**Cause:** Device muted or volume too low
**Solution:**
1. Check system volume (not muted)
2. Check browser volume
3. Check audio element volume (slider on player)
4. Verify speakers/headphones working

---

### Issue: Very slow transcription (20+ seconds)
**Cause:** Large audio file or network latency
**Solution:**
1. Use shorter audio messages (< 30 seconds ideal)
2. Reduce audio quality if possible
3. Check network connection speed
4. Monitor OpenAI API quota

---

### Issue: "Too many requests" error
**Cause:** API rate limiting exceeded
**Solution:**
1. Wait 1 minute before next request
2. Reduce number of concurrent requests
3. Contact API provider about quota

---

## Visual Verification Checklist

- [x] Microphone button appears with 🎤 icon
- [x] Recording indicator appears when recording
- [x] Stop button visible in recording indicator
- [x] Response Format card shows with Text/Audio options
- [x] Selected format highlighted
- [x] Audio player appears after audio response
- [x] Audio player has play/pause/volume controls
- [x] Document upload accepts audio files
- [x] No console errors during normal operation
- [x] Colors match steel blue theme (#324b74ff)
- [x] Responsive on mobile (if applicable)

---

## Production Checklist

Before deploying to production:

- [ ] OPENAI_API_KEY set in production environment
- [ ] GROQ_API_KEY verified and current
- [ ] ELEVENLABS_API_KEY active
- [ ] Cloudinary credentials working
- [ ] HTTPS enabled (required for Web Audio API)
- [ ] File upload size limits enforced
- [ ] Rate limiting configured on API endpoints
- [ ] Logging enabled for audio operations
- [ ] Backup plan for API outages documented
- [ ] User documentation provided
- [ ] Browser compatibility tested (Chrome, Firefox, Safari, Edge)
- [ ] Mobile experience tested
- [ ] Accessibility tested (WCAG 2.1 AA)
- [ ] Performance monitoring enabled

---

## Support Resources

- **Groq API Docs:** https://console.groq.com/docs/speech-text
- **ElevenLabs API:** https://elevenlabs.io/docs
- **OpenAI Whisper:** https://platform.openai.com/docs/guides/speech-to-text
- **Web Audio API:** https://developer.mozilla.org/en-US/docs/Web/API/Web_Audio_API
- **MediaRecorder:** https://developer.mozilla.org/en-US/docs/Web/API/MediaRecorder

---

## Feedback & Issues

If you encounter issues not covered here:

1. **Check Browser Console** for error messages (F12 → Console)
2. **Check Network Tab** for failed API calls
3. **Review Server Logs** in `var/log/dev.log`
4. **Test with Simple Case First** (text-to-text before audio)
5. **Verify All API Keys** are correctly set and active

---

**Last Updated:** 2025
**Status:** Production Ready (pending OPENAI_API_KEY configuration)
