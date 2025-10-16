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
      credentials: 'include', // <---- IMPORTANT
      body: JSON.stringify({ participant_id: code })
    });

    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
    }
    
    const data = await response.json();

    if (data.success) {
      alert(`Login successful! Your role is: ${data.role}`);
      console.log('Participant data:', data);

       // Store participant info for later stages; this goes to the browser
      sessionStorage.setItem('participant_id', data.participant_id);
      sessionStorage.setItem('role', data.role);
      sessionStorage.setItem('session_id', data.session_id);
      
      alert(`Login successful! Your role is: ${data.role}`);

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
    if (!data.success) return;

    console.log(data); // <---------------to delete


    const stage = data.data.stage;
    const completedStages = data.data.completed_stages || [];
    const role = sessionStorage.getItem('role') || 'Unknown';

    // If no active stage, show waiting
    if (!stage) {
      showScreen('waiting-screen');
      return;
    }

    const stageName = stage.stage_name;
    console.log('Stage:', stageName, 'Completed stages:', completedStages);

       // If participant already completed this stage, show waiting screen
    if (completedStages.includes(stageName)) {
      showScreen('waiting-screen');
      return;
    }
    
    

    // Determine which screen to show based on stage name
    if (stageName.startsWith('instructions')) {
      await showInstructions(stageName);
    } else if (stageName === 'strategy_method') {
      const role = sessionStorage.getItem('role') || 'Unknown'; // get role from session storage
      alert("you are in the straegy world");
      showStrategyScreen(role); // show the strategy screen for this participant
      stopPolling();
    } else if (stageName === 'effort_task') {
      
      const role = sessionStorage.getItem('role');
      
      if (role === 'A') {
        showEffortTask();
        stopPolling();
      } else {
        showScreen('waiting-screen');
      }
    } else if (stageName === 'results') {
      showScreen('results-screen');
      stopPolling();
    } else {
      showScreen('waiting-screen');
    }

  } catch (err) {
    console.error('Status error:', err);
  }
}




async function showInstructions(stageName) {
  const container = document.getElementById('instructions-screen');
  const role = sessionStorage.getItem('role') || 'Unknown';
  const participant_id = sessionStorage.getItem('participant_id');

  let html = `<h2>Instructions</h2><p>You are player <strong>${role}</strong>.</p>`;

  if (stageName.includes('type1')) {
    html += `<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Nulla facilisi. Phasellus non nulla at nunc ullamcorper fringilla.</p>`;
  } 
  else if (stageName.includes('type2')) {
    if (role === 'A') {
      // Only type A has penalties
      const penalty = await getPenaltyProbability(participant_id, role);
      html += `<p>The probability of a <strong>high penalty</strong> this round is <strong>${penalty}</strong>.</p>`;
    } else {
      html += `<p>The probability of a <strong>high penalty</strong> this round is <strong>N/A</strong>.</p>`;
    }
  } 
  else if (stageName.includes('type3')) {
    html += `<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Donec porta, magna in laoreet consequat, turpis sapien gravida nisi.</p>`;
  }

  html += `<p><em>Waiting for the next stage...</em></p>`;
  container.innerHTML = html;
  showScreen('instructions-screen');
}



async function getPenaltyProbability(participant_id, role) {
  // Only type A has penalties
  if (role !== 'A') return 'N/A';

  try {
    const response = await fetch('../api/get_penalty.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include', // <---- IMPORTANT
      body: JSON.stringify({ participant_id })
    });

    if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
    const data = await response.json();

    if (data.success) {
      return data.penalty_probability;
    } else {
      console.warn('Penalty not found:', data.message);
      return 'Unavailable';
    }

  } catch (err) {
    console.error('Error fetching penalty:', err);
    return 'Unavailable';
  }
}




async function showStrategyScreen(role) {
  const container = document.getElementById('strategy-questions');
  let html = '';

  if (role === 'A') {
    html += `
      <p>If you are successful and Player B requests, do you:</p>
      <label><input type="radio" name="success_request" value="accept"> Accept</label>
      <label><input type="radio" name="success_request" value="reject"> Reject</label>

      <p>If you are unsuccessful and Player B suggested, do you:</p>
      <label><input type="radio" name="fail_suggest" value="accept"> Accept</label>
      <label><input type="radio" name="fail_suggest" value="reject"> Reject</label>
    `;
  } else if (role === 'B') {
    html += `
      <p>If Player A is successful, do you:</p>
      <label><input type="radio" name="success_decision" value="request"> Request</label>
      <label><input type="radio" name="success_decision" value="do_nothing"> Do Nothing</label>

      <p>If Player A is unsuccessful, do you:</p>
      <label><input type="radio" name="fail_decision" value="suggest"> Suggest</label>
      <label><input type="radio" name="fail_decision" value="do_nothing"> Do Nothing</label>
    `;
  }

  container.innerHTML = html;
  showScreen('strategy-screen');

  document.getElementById('strategy-form').onsubmit = async function(e) {
    e.preventDefault();
    await submitDecisions(role);
  };
}


async function showEffortTask() {
  const role = sessionStorage.getItem('role');
  const container = document.getElementById('effort-task-screen');

  if (role !== 'A') {
    showScreen('waiting-screen');
    return;
  }

  // Only sliders for now
  const taskType = 'addnums'; //'sliders'; 
  sessionStorage.setItem('effort_task_type', taskType);

  // Fetch fragment
  const html = await fetch(`tasks/${taskType}.html`).then(res => res.text());

  container.innerHTML = html;
  showScreen('effort-task-screen');

  /// Initialize after DOM is updated
  requestAnimationFrame(() => {
    if (taskType === 'addnums') initAddNumsTask();
    else initSlidersTask();
  });

}


// --- Sliders task ---
function initSlidersTask() {
  const taskContainer = document.querySelector('#effort-task-screen #taskContainer');
  const numSliders = 60;
  const target = 50;
  const tolerance = 2;
  const sliders = [];

  for (let i = 0; i < numSliders; i++) {
    const block = document.createElement('div');
    block.classList.add('slider-block');

    const label = document.createElement('div');
    label.classList.add('value-label');

    const slider = document.createElement('input');
    slider.type = 'range';
    slider.min = 0;
    slider.max = 100;
    slider.value = Math.floor(Math.random() * 100);
    slider.classList.add('slider');

    label.innerText = slider.value;
    slider.addEventListener('input', () => label.innerText = slider.value);

    block.appendChild(label);
    block.appendChild(slider);
    taskContainer.appendChild(block);

    sliders.push(slider);
  }

  let remaining = 10; //<-------------SET TIME TO 60
  const timerEl = document.querySelector('#effort-task-screen #timer');
  const interval = setInterval(() => {
    remaining--;
    timerEl.textContent = remaining;
    if (remaining <= 0) {
      clearInterval(interval);
      const correctCount = sliders.filter(s => Math.abs(s.value - target) <= tolerance).length;
      submitEffortResult('sliders', correctCount);
    }
  }, 1000);
}

// --- Add numbers task ---
function initAddNumsTask() {
  const container = document.querySelector('#effort-task-screen');
  const timerEl = container.querySelector('#timer');
  const problemEl = container.querySelector('#problem');
  const answerEl = container.querySelector('#answer');
  const keypadEl = container.querySelector('#keypad');
  const submitBtn = container.querySelector('#submit');

  // Reset previous state
  keypadEl.innerHTML = '';
  answerEl.value = '';
  answerEl.disabled = false;
  submitBtn.disabled = false;

  let correctCount = 0;
  let currentAnswer = 0;
  let timeLeft = 10; //<-------------SET TIME TO 60

  function newProblem() {
    const a = Math.floor(Math.random() * 90) + 10;
    const b = Math.floor(Math.random() * 90) + 10;
    currentAnswer = a + b;
    problemEl.textContent = `${a} + ${b} = ?`;
    answerEl.value = '';
  }

  function handleKey(k) {
    if (k === 'C') answerEl.value = '';
    else if (k === '←') answerEl.value = answerEl.value.slice(0,-1);
    else answerEl.value += k;
  }

  // Generate keypad
  const keys = ['1','2','3','4','5','6','7','8','9','0','←','C'];
  keys.forEach(k => {
    const btn = document.createElement('button');
    btn.textContent = k;
    btn.className = 'key';
    btn.onclick = () => handleKey(k);
    keypadEl.appendChild(btn);
  });

  submitBtn.onclick = () => {
    if (parseInt(answerEl.value) === currentAnswer) correctCount++;
    newProblem();
  };

  const interval = setInterval(() => {
    timeLeft--;
    timerEl.textContent = timeLeft;
    if (timeLeft <= 0) {
      clearInterval(interval);
      submitBtn.disabled = true;
      answerEl.disabled = true;
      submitEffortResult('addnums', correctCount);
    }
  }, 1000);

  newProblem();
}



async function submitEffortResult(taskType, effortScore) {
  const participant_id = sessionStorage.getItem('participant_id');
  const session_id = sessionStorage.getItem('session_id');

 
 
  try {
    // Fetch current round_id
    const res = await fetch('../api/status.php');
    const statusData = await res.json();
    const round_id = statusData.data.round?.round_id;

     alert(`your id is ${participant_id} with round ${round_id}`);

    if (!round_id) {
      alert('Round not found.');
      showScreen('waiting-screen');
      return;
    }

    const payload = {
      participant_id,
      session_id,
      round_id,
      task_type: taskType,
      effort_score: effortScore
    };

    const response = await fetch('../api/submit_effort_task.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify(payload)
    });
 
    const data = await response.json();
    alert("Result"+JSON.stringify(data));
    if (data.success) {
      // Task recorded, show waiting screen
      showScreen('waiting-screen');
      startPolling(); // Keep polling for next stage
    } else {
      alert('Error submitting effort task: ' + data.message);
    }  
  } catch (err) {
    console.error(err);
    alert('Error submitting effort task. Please try again.');
  } 
 
}




async function submitDecisions(role) {
  const form = document.getElementById('strategy-form');
  const formData = new FormData(form);
  let decisions = {};

  // Collect selected decisions
  const allRadioNames = Array.from(form.querySelectorAll('input[type="radio"]'))
    .map(input => input.name)
    .filter((v, i, a) => a.indexOf(v) === i); // unique names

  let missing = [];

  allRadioNames.forEach(name => {
    const selected = form.querySelector(`input[name="${name}"]:checked`);
    if (selected) {
      decisions[name] = selected.value;
    } else {
      missing.push(name);
    }
  });

  if (missing.length > 0) {
    alert(`Please make a choice for: ${missing.join(', ')}`);
    return;
  }

  try {
    const res = await fetch('../api/strategy.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include', // <---- IMPORTANT
      body: JSON.stringify({ decisions })
    });

    const data = await res.json();

    if (data.success) {
      alert('Decisions saved! Waiting for the next stage...');

      await markStageComplete('strategy_method'); 

      showScreen('waiting-screen');
      startPolling(); 
    } else {
      alert('Error saving decisions: ' + data.message);
    }

  } catch (err) {
    console.error('Error submitting decisions:', err);
    alert('Error submitting decisions. Please try again.');
  }
}


async function markStageComplete(stageName) {
  const participant_id = sessionStorage.getItem('participant_id');
  const session_id = sessionStorage.getItem('session_id');

  const roundRes = await fetch('../api/status.php');
  const statusData = await roundRes.json();
  const round_id = statusData.data.round?.round_id;

  if (!round_id) return;

  await fetch('../api/mark_stage_complete.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ round_id, stage_name: stageName })
  });
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


 