(function () {
  'use strict';

  const USER_ID = window.APP_USER_ID;
  const LOCAL_KEY = 'plan_tasks_' + USER_ID;
  const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

  const listEl = document.getElementById('plan-list-page');
  const emptyStateEl = document.getElementById('plan-empty-state');
  const badgeEl = document.getElementById('plan-pending-badge');
  const addForm = document.getElementById('add-plan-form');
  const projectNameInput = document.getElementById('plan-project-name');
  const responsibleInput = document.getElementById('plan-responsible');
  const commentInput = document.getElementById('plan-comment');
  const fixedCheckbox = document.getElementById('plan-fixed');
  const btnMarkAll = document.getElementById('plan-btn-mark-all');
  const btnUnmarkAll = document.getElementById('plan-btn-unmark-all');
  const btnClearAll = document.getElementById('plan-btn-clear-all');
  const tabsEl = document.getElementById('plan-tabs');
  const emptyStateTextEl = document.getElementById('plan-empty-state-text');

  let fixedItems = [];
  let localItems = [];
  let drake = null;
  let activeTab = 'all';

  const EMPTY_STATE_TEXT = {
    all: 'No plan/task items yet. Add one above!',
    fixed: 'No fixed items yet.',
    local: 'No local items yet.',
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

  function loadLocalItems() {
    try {
      const raw = localStorage.getItem(LOCAL_KEY);
      const parsed = raw ? JSON.parse(raw) : [];
      return Array.isArray(parsed) ? parsed : [];
    } catch (e) {
      return [];
    }
  }

  function saveLocalItems() {
    localStorage.setItem(LOCAL_KEY, JSON.stringify(localItems));
  }

  function nextLocalOrder() {
    return localItems.reduce(function (max, t) {
      return Math.max(max, t.order || 0);
    }, 0) + 1;
  }

  // ---------- rendering ----------

  function render() {
    const sortedFixed = fixedItems.slice().sort(function (a, b) { return a.order - b.order; });
    const sortedLocal = localItems.slice().sort(function (a, b) { return a.order - b.order; });

    const showFixed = activeTab === 'all' || activeTab === 'fixed';
    const showLocal = activeTab === 'all' || activeTab === 'local';

    listEl.innerHTML = '';

    if (showFixed) {
      sortedFixed.forEach(function (item) {
        listEl.appendChild(createItemElement(item, 'fixed'));
      });
    }
    if (showLocal) {
      sortedLocal.forEach(function (item) {
        listEl.appendChild(createItemElement(item, 'local'));
      });
    }

    const visible = (showFixed ? sortedFixed : []).concat(showLocal ? sortedLocal : []);
    const total = visible.length;
    const pending = visible.filter(function (t) { return !t.completed; }).length;

    badgeEl.textContent = pending;
    emptyStateTextEl.textContent = EMPTY_STATE_TEXT[activeTab];
    emptyStateEl.classList.toggle('d-none', total > 0);
    listEl.classList.toggle('d-none', total === 0);
  }

  function metaLine(item) {
    const parts = [];
    if (item.responsible_person) {
      parts.push('Responsible: ' + escapeHtml(item.responsible_person));
    }
    if (item.comment) {
      parts.push('Comment: ' + escapeHtml(item.comment));
    }
    if (parts.length === 0) return '';
    return '<div class="todo-item-meta text-muted small ps-4 mt-1">' + parts.join(' &middot; ') + '</div>';
  }

  function createItemElement(item, source) {
    const li = document.createElement('li');
    li.className = 'todo-item d-flex flex-column px-2 py-2 mb-2 fade-in' +
      (item.completed ? ' todo-completed' : '');
    li.dataset.id = item.id;
    li.dataset.source = source;
    li.dataset.completed = item.completed ? 'true' : 'false';

    const pinHtml = source === 'fixed'
      ? '<i class="ri-pushpin-2-fill text-primary" title="Synced item"></i>'
      : '';

    li.innerHTML =
      '<div class="d-flex align-items-center gap-2">' +
        '<span class="todo-drag-handle" title="Drag to reorder"><i class="ri-draggable"></i></span>' +
        '<input type="checkbox" class="form-check-input todo-toggle m-0"' + (item.completed ? ' checked' : '') + '>' +
        pinHtml +
        '<span class="todo-text flex-grow-1' + (item.completed ? ' text-decoration-line-through text-muted' : '') + '">' +
          escapeHtml(item.project_name) +
        '</span>' +
        '<button type="button" class="btn btn-sm btn-link todo-edit-btn" title="Edit"><i class="ri-edit-line"></i></button>' +
        '<button type="button" class="btn btn-sm btn-link todo-delete-btn" title="Delete"><i class="ri-delete-bin-line"></i></button>' +
      '</div>' +
      metaLine(item);

    return li;
  }

  // ---------- add ----------

  addForm.addEventListener('submit', function (e) {
    e.preventDefault();
    const projectName = projectNameInput.value.trim();
    const responsible = responsibleInput.value.trim();
    const comment = commentInput.value.trim();
    if (projectName === '') return;

    if (fixedCheckbox.checked) {
      apiFetch('../api/plan_store.php', 'POST', {
        project_name: projectName,
        responsible_person: responsible,
        comment: comment,
      })
        .then(function (data) {
          fixedItems.push(data.item);
          resetForm();
          render();
        })
        .catch(function () {
          showError('Could not add item.');
        });
    } else {
      localItems.push({
        id: 'local_' + Date.now() + '_' + Math.random().toString(36).slice(2, 8),
        project_name: projectName,
        responsible_person: responsible || null,
        comment: comment || null,
        completed: false,
        order: nextLocalOrder(),
        is_fixed: false,
      });
      saveLocalItems();
      resetForm();
      render();
    }
  });

  function resetForm() {
    projectNameInput.value = '';
    responsibleInput.value = '';
    commentInput.value = '';
    fixedCheckbox.checked = false;
  }

  // ---------- toggle / edit / delete (event delegation) ----------

  listEl.addEventListener('change', function (e) {
    if (!e.target.classList.contains('todo-toggle')) return;

    const li = e.target.closest('li');
    const id = li.dataset.id;
    const source = li.dataset.source;
    const completed = e.target.checked;

    if (source === 'fixed') {
      const item = fixedItems.find(function (t) { return String(t.id) === String(id); });
      const previous = item.completed;
      item.completed = completed;
      render();

      apiFetch('../api/plan_toggle.php', 'POST', { id: parseInt(id, 10), completed: completed })
        .catch(function () {
          item.completed = previous;
          render();
          showError('Could not update item.');
        });
    } else {
      const item = localItems.find(function (t) { return String(t.id) === String(id); });
      item.completed = completed;
      saveLocalItems();
      render();
    }
  });

  listEl.addEventListener('click', function (e) {
    const editBtn = e.target.closest('.todo-edit-btn');
    const delBtn = e.target.closest('.todo-delete-btn');

    if (editBtn) {
      startEdit(editBtn.closest('li'));
    } else if (delBtn) {
      deleteItem(delBtn.closest('li'));
    }
  });

  function deleteItem(li) {
    const id = li.dataset.id;
    const source = li.dataset.source;

    if (source === 'fixed') {
      apiFetch('../api/plan_destroy.php', 'POST', { id: parseInt(id, 10) })
        .then(function () {
          fixedItems = fixedItems.filter(function (t) { return String(t.id) !== String(id); });
          render();
        })
        .catch(function () {
          showError('Could not delete item.');
        });
    } else {
      localItems = localItems.filter(function (t) { return String(t.id) !== String(id); });
      saveLocalItems();
      render();
    }
  }

  function startEdit(li) {
    const id = li.dataset.id;
    const source = li.dataset.source;
    const collection = source === 'fixed' ? fixedItems : localItems;
    const item = collection.find(function (t) { return String(t.id) === String(id); });
    if (!item || !window.Swal) return;

    Swal.fire({
      title: 'Edit item',
      html:
        '<input id="swal-project-name" class="swal2-input" placeholder="Project name" maxlength="500" value="' + escapeHtml(item.project_name) + '">' +
        '<input id="swal-responsible" class="swal2-input" placeholder="Responsible person" maxlength="200" value="' + escapeHtml(item.responsible_person || '') + '">' +
        '<input id="swal-comment" class="swal2-input" placeholder="Comment" maxlength="1000" value="' + escapeHtml(item.comment || '') + '">',
      focusConfirm: false,
      showCancelButton: true,
      confirmButtonText: 'Save',
      preConfirm: function () {
        const projectName = document.getElementById('swal-project-name').value.trim();
        if (projectName === '') {
          Swal.showValidationMessage('Project name is required.');
          return false;
        }
        return {
          project_name: projectName,
          responsible_person: document.getElementById('swal-responsible').value.trim(),
          comment: document.getElementById('swal-comment').value.trim(),
        };
      },
    }).then(function (result) {
      if (!result.isConfirmed) return;

      const updated = result.value;

      if (source === 'fixed') {
        apiFetch('../api/plan_update.php', 'POST', {
          id: parseInt(id, 10),
          project_name: updated.project_name,
          responsible_person: updated.responsible_person,
          comment: updated.comment,
        })
          .then(function () {
            item.project_name = updated.project_name;
            item.responsible_person = updated.responsible_person || null;
            item.comment = updated.comment || null;
            render();
          })
          .catch(function () {
            showError('Could not update item.');
          });
      } else {
        item.project_name = updated.project_name;
        item.responsible_person = updated.responsible_person || null;
        item.comment = updated.comment || null;
        saveLocalItems();
        render();
      }
    });
  }

  // ---------- tabs ----------

  tabsEl.addEventListener('change', function (e) {
    if (!e.target.classList.contains('btn-check')) return;

    activeTab = e.target.dataset.tab;
    render();
  });

  // ---------- mark all / unmark all / clear all (scoped to active tab) ----------

  function setAllCompleted(completed) {
    const touchFixed = activeTab === 'all' || activeTab === 'fixed';
    const touchLocal = activeTab === 'all' || activeTab === 'local';

    if (touchFixed) {
      fixedItems.forEach(function (t) { t.completed = completed; });
    }
    if (touchLocal) {
      localItems.forEach(function (t) { t.completed = completed; });
      saveLocalItems();
    }
    render();

    if (touchFixed) {
      apiFetch('../api/plan_toggle_all.php', 'POST', { completed: completed })
        .catch(function () {
          showError('Could not update items.');
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
      if (touchFixed) fixedItems = [];
      if (touchLocal) {
        localItems = [];
        saveLocalItems();
      }
      render();
    };

    const doClear = function () {
      if (touchFixed) {
        apiFetch('../api/plan_clear_all.php', 'POST', {})
          .then(clearLocalState)
          .catch(function () {
            showError('Could not clear items.');
          });
      } else {
        clearLocalState();
      }
    };

    const confirmText = activeTab === 'fixed'
      ? 'Clear all fixed items?'
      : activeTab === 'local'
        ? 'Clear all local items?'
        : 'Clear all items?';

    if (window.Swal) {
      Swal.fire({
        title: confirmText,
        text: 'This will permanently delete the items in this tab. This cannot be undone.',
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
    drake = dragula([listEl], {
      moves: function (el, source, handle) {
        return !!handle.closest('.todo-drag-handle');
      },
    });

    drake.on('drop', function () {
      handleReorder();
    });
  }

  function handleReorder() {
    const items = Array.from(listEl.children);
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
      const item = fixedItems.find(function (t) { return String(t.id) === String(id); });
      if (item) item.order = index + 1;
    });
    localIds.forEach(function (id, index) {
      const item = localItems.find(function (t) { return String(t.id) === String(id); });
      if (item) item.order = index + 1;
    });

    saveLocalItems();
    render();

    if (fixedIds.length > 0) {
      apiFetch('../api/plan_reorder.php', 'POST', { order: fixedIds.map(Number) })
        .catch(function () {
          showError('Could not save item order.');
        });
    }
  }

  // ---------- init ----------

  function init() {
    initTheme();
    localItems = loadLocalItems();
    render();

    apiFetch('../api/plan_items.php', 'GET')
      .then(function (data) {
        fixedItems = data.items;
        render();
      })
      .catch(function () {
        showError('Could not load items.');
      });

    initDragula();
  }

  init();
})();
