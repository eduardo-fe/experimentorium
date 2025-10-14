// --- Show a specific screen ---
function showScreen(id) {
  const screens = document.querySelectorAll('.screen');
  screens.forEach(s => s.style.display = 'none');
  const target = document.getElementById(id);
  if (target) target.style.display = 'block';
}


async function loginParticipant() {
  const code = document.getElementById('participant-id').value.trim();
  if (!code) {
    alert('Please enter your ID.');
    return;
  }

  try {
    const response = await fetch('../api/login.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ participant_id: code })
    });

    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
    }
    
    const data = await response.json();

    if (data.success) {
      alert(`Login successful! Your role is: ${data.message}`);
      console.log('Participant data:', data);
      
      showScreen('waiting-screen');
      startPolling(); // start waiting for the round/stage

    } else {
      alert(`Login failed: ${data.message}`);
    }

  } catch (error) {
    console.error('Error:', error);
    alert(`Login error: ${error.message}`);
  }
}


// --- Polling function to get current session/round/stage ---
async function getStatus() {
  try {
    const res = await fetch('../api/status.php');
    if (!res.ok) throw new Error(`HTTP error! status: ${res.status}`);

    const data = await res.json();
    if (data.success) {
      const stage = data.data.stage;
      if (stage) {
        // Stage exists → show stage screen
        document.getElementById('strategy-screen').textContent = stage.stage_name;
        showScreen('strategy-screen');
        alert(JSON.stringify(stage));  //<--------------------------------------------TO BE DELETED.
        stopPolling();
        
      } else {
        // No stage yet → keep waiting
        showScreen('waiting-screen');
      }
    }

  } catch (err) {
    console.error('Status error:', err);
  }
}


// --- Start polling every few seconds ---
let pollingInterval = null; // store interval ID globally

function startPolling() {
  if (pollingInterval) clearInterval(pollingInterval); // avoid double intervals
  getStatus(); // immediate check
  pollingInterval = setInterval(getStatus, 4000); // store interval ID
}

// --- Stop polling ---
function stopPolling() {
  if (pollingInterval) {
    clearInterval(pollingInterval);
    pollingInterval = null;
  }
}


// Attach event listener to login form
document.getElementById('login-form').addEventListener('submit', function (e) {
  e.preventDefault();
  loginParticipant();
});


 