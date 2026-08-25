(function () {
  'use strict';

  const USER_ID = window.APP_USER_ID;
  const LOCAL_KEY = 'tasks_' + USER_ID;
  const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

  const todoListEl = document.getElementById('todo-list-page');
  const emptyStateEl = document.getElementById('empty-state');
  const badgeEl = document.getElementById('pending-badge');
  const addForm = document.getElementById('add-todo-form');
  const textInput = document.getElementById('todo-text');
  const fixedCheckbox = document.getElementById('todo-fixed');
  const btnMarkAll = document.getElementById('btn-mark-all');
  const btnUnmarkAll = document.getElementById('btn-unmark-all');
  const btnClearAll = document.getElementById('btn-clear-all');
  const todoTabsEl = document.getElementById('todo-tabs');
  const emptyStateTextEl = document.getElementById('empty-state-text');

  let fixedTasks = [];
  let localTasks = [];
  let drake = null;
  let activeTab = 'all';

  const EMPTY_STATE_TEXT = {
    all: 'No tasks yet. Add one above!',
    fixed: 'No fixed tasks yet.',
    local: 'No local tasks yet.',
  };

  // ---------- utilities ----------

  function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
  }

  function showError(message) {
    if (window.Swal) {
      Swal.fire({
        icon: 'error',
        title: 'Oops...',
        text: message,
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
      });
    } else {
      alert(message);
    }
  }

  function apiFetch(url, method, body) {
    const opts = {
      method: method,
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': CSRF_TOKEN,
      },
    };
    if (body !== undefined) {
      opts.body = JSON.stringify(body);
    }

    return fetch(url, opts)
      .then(function (res) {
        return res.json().then(function (json) {
          if (!res.ok || json.status !== 'success') {
            throw new Error(json.message || 'Request failed.');
          }
          return json.data;
        });
      });
  }

  // ---------- theme ----------

  function initTheme() {
    const toggleBtn = document.getElementById('theme-toggle');
    const toggleIcon = document.getElementById('theme-toggle-icon');
    if (!toggleBtn || !toggleIcon) return;

    function applyIcon(theme) {
      toggleIcon.className = theme === 'dark' ? 'ri-sun-line' : 'ri-moon-line';
      toggleBtn.setAttribute('aria-pressed', theme === 'dark' ? 'true' : 'false');
    }

    applyIcon(document.documentElement.getAttribute('data-bs-theme') || 'light');

    toggleBtn.addEventListener('click', function () {
      const current = document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'dark' : 'light';
      const next = current === 'dark' ? 'light' : 'dark';
      document.documentElement.setAttribute('data-bs-theme', next);
      localStorage.setItem('theme', next);
      applyIcon(next);
    });
  }

  // ---------- local storage ----------

  function loadLocalTasks() {
    try {
      const raw = localStorage.getItem(LOCAL_KEY);
      const parsed = raw ? JSON.parse(raw) : [];
      return Array.isArray(parsed) ? parsed : [];
    } catch (e) {
      return [];
    }
  }

  function saveLocalTasks() {
    localStorage.setItem(LOCAL_KEY, JSON.stringify(localTasks));
  }

  function nextLocalOrder() {
    return localTasks.reduce(function (max, t) {
      return Math.max(max, t.order || 0);
    }, 0) + 1;
  }

  // ---------- rendering ----------

  function render() {
    const sortedFixed = fixedTasks.slice().sort(function (a, b) { return a.order - b.order; });
    const sortedLocal = localTasks.slice().sort(function (a, b) { return a.order - b.order; });

    const showFixed = activeTab === 'all' || activeTab === 'fixed';
    const showLocal = activeTab === 'all' || activeTab === 'local';

    todoListEl.innerHTML = '';

    if (showFixed) {
      sortedFixed.forEach(function (task) {
        todoListEl.appendChild(createTaskElement(task, 'fixed'));
      });
    }
    if (showLocal) {
      sortedLocal.forEach(function (task) {
        todoListEl.appendChild(createTaskElement(task, 'local'));
      });
    }

    const visible = (showFixed ? sortedFixed : []).concat(showLocal ? sortedLocal : []);
    const total = visible.length;
    const pending = visible.filter(function (t) { return !t.completed; }).length;

    badgeEl.textContent = pending;
    emptyStateTextEl.textContent = EMPTY_STATE_TEXT[activeTab];
    emptyStateEl.classList.toggle('d-none', total > 0);
    todoListEl.classList.toggle('d-none', total === 0);
  }

  function createTaskElement(task, source) {
    const li = document.createElement('li');
    li.className = 'todo-item d-flex align-items-center gap-2 px-2 py-2 mb-2 fade-in' +
      (task.completed ? ' todo-completed' : '');
    li.dataset.id = task.id;
    li.dataset.source = source;
    li.dataset.completed = task.completed ? 'true' : 'false';

    const pinHtml = source === 'fixed'
      ? '<i class="ri-pushpin-2-fill text-primary" title="Synced task"></i>'
      : '';

    li.innerHTML =
      '<span class="todo-drag-handle" title="Drag to reorder"><i class="ri-draggable"></i></span>' +
      '<input type="checkbox" class="form-check-input todo-toggle m-0"' + (task.completed ? ' checked' : '') + '>' +
      pinHtml +
      '<span class="todo-text flex-grow-1' + (task.completed ? ' text-decoration-line-through text-muted' : '') + '">' +
        escapeHtml(task.text) +
      '</span>' +
      '<button type="button" class="btn btn-sm btn-link todo-edit-btn" title="Edit"><i class="ri-edit-line"></i></button>' +
      '<button type="button" class="btn btn-sm btn-link todo-delete-btn" title="Delete"><i class="ri-delete-bin-line"></i></button>';

    return li;
  }

  // ---------- add ----------

  addForm.addEventListener('submit', function (e) {
    e.preventDefault();
    const text = textInput.value.trim();
    if (text === '') return;

    if (fixedCheckbox.checked) {
      apiFetch('../api/todo_store.php', 'POST', { text: text })
        .then(function (data) {
          fixedTasks.push(data.todo);
          textInput.value = '';
          fixedCheckbox.checked = false;
          render();
        })
        .catch(function () {
          showError('Could not add task.');
        });
    } else {
      localTasks.push({
        id: 'local_' + Date.now() + '_' + Math.random().toString(36).slice(2, 8),
        text: text,
        completed: false,
        order: nextLocalOrder(),
        is_fixed: false,
      });
      saveLocalTasks();
      textInput.value = '';
      fixedCheckbox.checked = false;
      render();
    }
  });

  // ---------- toggle / edit / delete (event delegation) ----------

  todoListEl.addEventListener('change', function (e) {
    if (!e.target.classList.contains('todo-toggle')) return;

    const li = e.target.closest('li');
    const id = li.dataset.id;
    const source = li.dataset.source;
    const completed = e.target.checked;

    if (source === 'fixed') {
      const task = fixedTasks.find(function (t) { return String(t.id) === String(id); });
      const previous = task.completed;
      task.completed = completed;
      render();

      apiFetch('../api/todo_toggle.php', 'POST', { id: parseInt(id, 10), completed: completed })
        .catch(function () {
          task.completed = previous;
          render();
          showError('Could not update task.');
        });
    } else {
      const task = localTasks.find(function (t) { return String(t.id) === String(id); });
      task.completed = completed;
      saveLocalTasks();
      render();
    }
  });

  todoListEl.addEventListener('click', function (e) {
    const editBtn = e.target.closest('.todo-edit-btn');
    const delBtn = e.target.closest('.todo-delete-btn');

    if (editBtn) {
      startEdit(editBtn.closest('li'));
    } else if (delBtn) {
      deleteTask(delBtn.closest('li'));
    }
  });

  function deleteTask(li) {
    const id = li.dataset.id;
    const source = li.dataset.source;

    if (source === 'fixed') {
      apiFetch('../api/todo_destroy.php', 'POST', { id: parseInt(id, 10) })
        .then(function () {
          fixedTasks = fixedTasks.filter(function (t) { return String(t.id) !== String(id); });
          render();
        })
        .catch(function () {
          showError('Could not delete task.');
        });
    } else {
      localTasks = localTasks.filter(function (t) { return String(t.id) !== String(id); });
      saveLocalTasks();
      render();
    }
  }

  function startEdit(li) {
    if (li.classList.contains('editing')) return;
    li.classList.add('editing');

    const span = li.querySelector('.todo-text');
    const original = span.textContent;

    const input = document.createElement('input');
    input.type = 'text';
    input.maxLength = 500;
    input.className = 'form-control form-control-sm todo-edit-input flex-grow-1';
    input.value = original;

    span.replaceWith(input);
    input.focus();
    input.setSelectionRange(input.value.length, input.value.length);

    let finished = false;

    function restoreSpan(text) {
      const newSpan = document.createElement('span');
      newSpan.className = 'todo-text flex-grow-1' + (li.dataset.completed === 'true' ? ' text-decoration-line-through text-muted' : '');
      newSpan.textContent = text;
      input.replaceWith(newSpan);
      li.classList.remove('editing');
    }

    function commit(save) {
      if (finished) return;
      finished = true;

      const newText = input.value.trim();
      const id = li.dataset.id;
      const source = li.dataset.source;

      if (!save || newText === '' || newText === original) {
        restoreSpan(original);
        return;
      }

      restoreSpan(newText);

      if (source === 'fixed') {
        apiFetch('../api/todo_update.php', 'POST', { id: parseInt(id, 10), text: newText })
          .then(function () {
            const task = fixedTasks.find(function (t) { return String(t.id) === String(id); });
            task.text = newText;
          })
          .catch(function () {
            showError('Could not update task.');
            render();
          });
      } else {
        const task = localTasks.find(function (t) { return String(t.id) === String(id); });
        task.text = newText;
        saveLocalTasks();
      }
    }

    input.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        commit(true);
      } else if (e.key === 'Escape') {
        e.preventDefault();
        commit(false);
      }
    });

    input.addEventListener('blur', function () {
      commit(true);
    });
  }

  // ---------- tabs ----------

  todoTabsEl.addEventListener('change', function (e) {
    if (!e.target.classList.contains('btn-check')) return;

    activeTab = e.target.dataset.tab;
    render();
  });

  // ---------- mark all / unmark all / clear all (scoped to active tab) ----------

  function setAllCompleted(completed) {
    const touchFixed = activeTab === 'all' || activeTab === 'fixed';
    const touchLocal = activeTab === 'all' || activeTab === 'local';

    if (touchFixed) {
      fixedTasks.forEach(function (t) { t.completed = completed; });
    }
    if (touchLocal) {
      localTasks.forEach(function (t) { t.completed = completed; });
      saveLocalTasks();
    }
    render();

    if (touchFixed) {
      apiFetch('../api/todo_toggle_all.php', 'POST', { completed: completed })
        .catch(function () {
          showError('Could not update tasks.');
        });
    }
  }

  btnMarkAll.addEventListener('click', function () {
    setAllCompleted(true);
  });

  btnUnmarkAll.addEventListener('click', function () {
    setAllCompleted(false);
  });

  btnClearAll.addEventListener('click', function () {
    const touchFixed = activeTab === 'all' || activeTab === 'fixed';
    const touchLocal = activeTab === 'all' || activeTab === 'local';

    const clearLocalState = function () {
      if (touchFixed) fixedTasks = [];
      if (touchLocal) {
        localTasks = [];
        saveLocalTasks();
      }
      render();
    };

    const doClear = function () {
      if (touchFixed) {
        apiFetch('../api/todo_clear_all.php', 'POST', {})
          .then(clearLocalState)
          .catch(function () {
            showError('Could not clear tasks.');
          });
      } else {
        clearLocalState();
      }
    };

    const confirmText = activeTab === 'fixed'
      ? 'Clear all fixed tasks?'
      : activeTab === 'local'
        ? 'Clear all local tasks?'
        : 'Clear all tasks?';

    if (window.Swal) {
      Swal.fire({
        title: confirmText,
        text: 'This will permanently delete the tasks in this tab. This cannot be undone.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, clear all',
        confirmButtonColor: '#dc3545',
      }).then(function (result) {
        if (result.isConfirmed) doClear();
      });
    } else if (confirm(confirmText + ' This cannot be undone.')) {
      doClear();
    }
  });

  // ---------- drag to reorder ----------

  function initDragula() {
    drake = dragula([todoListEl], {
      moves: function (el, source, handle) {
        return !!handle.closest('.todo-drag-handle');
      },
    });

    drake.on('drop', function () {
      handleReorder();
    });
  }

  function handleReorder() {
    const items = Array.from(todoListEl.children);
    const fixedIds = [];
    const localIds = [];

    items.forEach(function (li) {
      if (li.dataset.source === 'fixed') {
        fixedIds.push(li.dataset.id);
      } else {
        localIds.push(li.dataset.id);
      }
    });

    fixedIds.forEach(function (id, index) {
      const task = fixedTasks.find(function (t) { return String(t.id) === String(id); });
      if (task) task.order = index + 1;
    });
    localIds.forEach(function (id, index) {
      const task = localTasks.find(function (t) { return String(t.id) === String(id); });
      if (task) task.order = index + 1;
    });

    saveLocalTasks();
    render();

    if (fixedIds.length > 0) {
      apiFetch('../api/todo_reorder.php', 'POST', { order: fixedIds.map(Number) })
        .catch(function () {
          showError('Could not save task order.');
        });
    }
  }

  // ---------- init ----------

  function init() {
    initTheme();
    localTasks = loadLocalTasks();
    render();

    apiFetch('../api/todo_items.php', 'GET')
      .then(function (data) {
        fixedTasks = data.todos;
        render();
      })
      .catch(function () {
        showError('Could not load tasks.');
      });

    initDragula();
  }

  init();
})();
