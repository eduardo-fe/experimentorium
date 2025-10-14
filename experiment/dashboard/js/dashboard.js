// ===============================
// Dashboard JS
// Handles session, rounds, and status updates
// ===============================

// --- 1. Fetch current status and update dashboard ---
async function updateStatus() {
  try {
    const response = await fetch('../api/status.php');
    const data = await response.json();

    if (data.success) {
      const info = data.data;
      document.getElementById('sessionInfo').innerHTML = `
        <strong>Session:</strong> ${info.session ? info.session.session_name : 'None'}<br>
        <strong>Round:</strong> ${info.round ? 'Round ' + info.round.round_number : 'None'}<br>
        <strong>Stage:</strong> ${info.stage ? info.stage.stage_name : 'None'}
      `;
    } else {
      document.getElementById('sessionInfo').textContent = 'Failed to load status.';
    }
  } catch (err) {
    console.error(err);
    document.getElementById('sessionInfo').textContent = 'Error fetching status.';
  }
}


// Call it once initially
updateStatus();

// Optional: poll every 5 seconds
setInterval(updateStatus, 300);



async function manageSession(action) {
  
try {
    const response = await fetch('../api/session.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({action})
    });

    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
    }
    /*
    const data = await response.json();
    */
   const text = await response.text();
console.log('Raw response:', text);
const data = JSON.parse(text);



    if (data.success) {
      document.getElementById('status').textContent = `Order executed. ${data.message}`;
    } else {
      document.getElementById('status').textContent = `Order failed. ${data.message} with number ${data.session_id}`;
    }

  } catch (error) {
    console.error('Error:', error);
    document.getElementById('status').textContent = `Error. ${error.message}`;
  }


}


document
  .getElementById('createSessionBtn')
  .addEventListener('click', ()=> manageSession('create'));

document
  .getElementById('deleteSessionBtn')
  .addEventListener('click', ()=> manageSession('delete'));

document
  .getElementById('createRound1Btn')
  .addEventListener('click', () => manageSession('create_round_1'));

document
  .getElementById('createRound2Btn')
  .addEventListener('click', () => manageSession('create_round_2'));

document
  .getElementById('createRound3Btn')
  .addEventListener('click', () => manageSession('create_round_3'));

document
  .getElementById('deleteCurrentRoundBtn')
  .addEventListener('click', ()=> manageSession('delete_round'));

document
  .getElementById('nextStageBtn')
  .addEventListener('click', ()=> manageSession('next_stage'));


  