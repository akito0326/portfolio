const loginPanel = document.querySelector("#login-panel");
const adminPanel = document.querySelector("#admin-panel");
const loginForm = document.querySelector("#login-form");
const loginMessage = document.querySelector("#login-message");
const adminMessage = document.querySelector("#admin-message");
const projectsList = document.querySelector("#projects-list");
const buildLogsList = document.querySelector("#build-logs-list");
const projectDialog = document.querySelector("#project-dialog");
const buildLogDialog = document.querySelector("#build-log-dialog");
const projectForm = document.querySelector("#project-form");
const buildLogForm = document.querySelector("#build-log-form");

class ApiError extends Error {
  constructor(message, status) {
    super(message);
    this.name = "ApiError";
    this.status = status;
  }
}

async function request(path, options = {}) {
  const headers = new Headers(options.headers);
  headers.set("Accept", "application/json");

  if (options.body !== undefined) {
    headers.set("Content-Type", "application/json");
  }

  const response = await fetch(`/api${path}`, {
    ...options,
    headers,
    credentials: "same-origin",
  });
  let body;
  try {
    body = await response.json();
  } catch {
    throw new Error(`APIからJSON以外の応答が返されました (${response.status})`);
  }

  if (!response.ok) {
    throw new ApiError(
      body?.error?.message ?? `API request failed (${response.status})`,
      response.status,
    );
  }

  return body;
}

function setMessage(element, message = "") {
  element.textContent = message;
}

function showLogin(message = "") {
  adminPanel.hidden = true;
  loginPanel.hidden = false;
  setMessage(loginMessage, message);
}

function showAdmin(username) {
  loginPanel.hidden = true;
  adminPanel.hidden = false;
  document.querySelector("#account-name").textContent = username;
  setMessage(loginMessage);
}

function createElement(tag, className, text) {
  const element = document.createElement(tag);
  if (className) element.className = className;
  if (text !== undefined) element.textContent = text;
  return element;
}

function actionButton(label, action, danger = false) {
  const button = createElement("button", `button quiet${danger ? " danger" : ""}`, label);
  button.type = "button";
  button.addEventListener("click", action);
  return button;
}

function addEmptyState(container, message) {
  container.replaceChildren(createElement("p", "empty-state", message));
}

function projectRecord(project) {
  const record = createElement("article", "record");
  const copy = createElement("div", "record-copy");
  const meta = createElement(
    "p",
    "record-meta",
    `${project.number} · ${project.slug} · ${project.stack.join(" / ")}`,
  );
  const title = createElement("h3", "", project.title);
  const description = createElement("p", "", project.description);
  const actions = createElement("div", "record-actions");

  copy.append(meta, title, description);
  actions.append(
    actionButton("編集", () => openProjectEditor(project)),
    actionButton("削除", () => deleteProject(project), true),
  );
  record.append(copy, actions);
  return record;
}

function buildLogRecord(log) {
  const record = createElement("article", "record");
  const copy = createElement("div", "record-copy");
  const visibility = log.is_public ? "公開中" : "非公開";
  const meta = createElement(
    "p",
    "record-meta",
    `${log.logged_at} · ${log.tag} · ${visibility} · 順序 ${log.sort_order}`,
  );
  const title = createElement("h3", "", log.title);
  const actions = createElement("div", "record-actions");

  copy.append(meta, title);
  actions.append(
    actionButton("編集", () => openBuildLogEditor(log)),
    actionButton("削除", () => deleteBuildLog(log), true),
  );
  record.append(copy, actions);
  return record;
}

async function loadRecords() {
  setMessage(adminMessage, "データを読み込み中...");
  try {
    const [projects, buildLogs] = await Promise.all([
      request("/projects"),
      request("/admin/build-logs"),
    ]);

    if (!Array.isArray(projects) || !Array.isArray(buildLogs)) {
      throw new Error("APIから想定外のデータが返されました。");
    }

    if (projects.length === 0) {
      addEmptyState(projectsList, "作品がありません。");
    } else {
      projectsList.replaceChildren(...projects.map(projectRecord));
    }

    if (buildLogs.length === 0) {
      addEmptyState(buildLogsList, "制作ログがありません。");
    } else {
      buildLogsList.replaceChildren(...buildLogs.map(buildLogRecord));
    }
    setMessage(adminMessage);
  } catch (error) {
    if (error instanceof ApiError && error.status === 401) {
      showLogin("セッションが切れました。もう一度ログインしてください。");
      return;
    }
    setMessage(adminMessage, error instanceof Error ? error.message : "データを読み込めませんでした。");
  }
}

function field(form, name) {
  return form.elements.namedItem(name);
}

function setValue(form, name, value) {
  field(form, name).value = value ?? "";
}

function toLines(value) {
  return value
    .split(/\r?\n/)
    .map((item) => item.trim())
    .filter(Boolean);
}

function openProjectEditor(project = null) {
  projectForm.reset();
  document.querySelector("#project-dialog-title").textContent = project ? "作品を編集" : "作品を追加";
  setValue(projectForm, "id", project?.id ?? "");
  setValue(projectForm, "display_order", project?.number ? Number(project.number) : "");
  setValue(projectForm, "slug", project?.slug);
  setValue(projectForm, "title", project?.title);
  setValue(projectForm, "description", project?.description);
  setValue(projectForm, "accent", project?.accent);
  setValue(projectForm, "visual", project?.visual);
  setValue(projectForm, "stack", project?.stack?.join("\n"));
  setValue(projectForm, "github", project?.links?.github);
  setValue(projectForm, "demo", project?.links?.demo);
  setValue(projectForm, "overview", project?.overview);
  setValue(projectForm, "problem", project?.problem);
  setValue(projectForm, "solution", project?.solution);
  setValue(projectForm, "features", project?.features?.join("\n"));
  setValue(projectForm, "architecture", project?.architecture?.join("\n"));
  projectDialog.showModal();
}

function openBuildLogEditor(log = null) {
  buildLogForm.reset();
  document.querySelector("#build-log-dialog-title").textContent = log ? "制作ログを編集" : "制作ログを追加";
  setValue(buildLogForm, "id", log?.id ?? "");
  setValue(buildLogForm, "title", log?.title);
  setValue(buildLogForm, "tag", log?.tag);
  setValue(buildLogForm, "logged_at", log?.logged_at);
  setValue(buildLogForm, "sort_order", log?.sort_order ?? 0);
  field(buildLogForm, "is_public").checked = log?.is_public ?? true;
  buildLogDialog.showModal();
}

function projectPayload(form) {
  const data = new FormData(form);
  return {
    display_order: Number(data.get("display_order")),
    slug: data.get("slug").trim(),
    title: data.get("title").trim(),
    description: data.get("description").trim(),
    accent: data.get("accent").trim(),
    visual: data.get("visual").trim(),
    stack: toLines(data.get("stack")),
    links: {
      github: data.get("github").trim(),
      demo: data.get("demo").trim(),
    },
    overview: data.get("overview").trim(),
    problem: data.get("problem").trim(),
    solution: data.get("solution").trim(),
    features: toLines(data.get("features")),
    architecture: toLines(data.get("architecture")),
  };
}

function buildLogPayload(form) {
  const data = new FormData(form);
  return {
    title: data.get("title").trim(),
    tag: data.get("tag").trim(),
    logged_at: data.get("logged_at"),
    sort_order: Number(data.get("sort_order")),
    is_public: data.has("is_public"),
  };
}

async function saveRecord(form, dialog, path, payload) {
  const id = field(form, "id").value;
  const method = id ? "PUT" : "POST";
  const endpoint = id ? `${path}/${encodeURIComponent(id)}` : path;
  const submit = form.querySelector('[type="submit"]');
  submit.disabled = true;

  try {
    await request(endpoint, {
      method,
      body: JSON.stringify(payload),
    });
    dialog.close();
    setMessage(adminMessage, "保存しました。");
    await loadRecords();
  } catch (error) {
    if (error instanceof ApiError && error.status === 401) {
      dialog.close();
      showLogin("セッションが切れました。もう一度ログインしてください。");
      return;
    }
    setMessage(adminMessage, error instanceof Error ? error.message : "保存に失敗しました。");
  } finally {
    submit.disabled = false;
  }
}

async function deleteRecord(path, record, label) {
  if (!window.confirm(`「${label}」を削除します。この操作は取り消せません。`)) return;

  try {
    await request(`${path}/${encodeURIComponent(record.id)}`, { method: "DELETE" });
    setMessage(adminMessage, "削除しました。");
    await loadRecords();
  } catch (error) {
    if (error instanceof ApiError && error.status === 401) {
      showLogin("セッションが切れました。もう一度ログインしてください。");
      return;
    }
    setMessage(adminMessage, error instanceof Error ? error.message : "削除に失敗しました。");
  }
}

function deleteProject(project) {
  return deleteRecord("/projects", project, project.title);
}

function deleteBuildLog(log) {
  return deleteRecord("/build-logs", log, log.title);
}

loginForm.addEventListener("submit", async (event) => {
  event.preventDefault();
  const submit = loginForm.querySelector('[type="submit"]');
  submit.disabled = true;
  setMessage(loginMessage);

  try {
    const data = new FormData(loginForm);
    const session = await request("/login", {
      method: "POST",
      body: JSON.stringify({
        username: data.get("username"),
        password: data.get("password"),
      }),
    });
    if (session?.authenticated !== true || typeof session.username !== "string") {
      throw new Error("ログインAPIから想定外の応答が返されました。");
    }
    showAdmin(session.username);
    loginForm.reset();
    await loadRecords();
  } catch (error) {
    setMessage(loginMessage, error instanceof Error ? error.message : "ログインに失敗しました。");
  } finally {
    submit.disabled = false;
  }
});

document.querySelector("#logout-button").addEventListener("click", async (event) => {
  const button = event.currentTarget;
  button.disabled = true;
  try {
    await request("/logout", { method: "POST" });
    showLogin("ログアウトしました。");
  } catch (error) {
    setMessage(adminMessage, error instanceof Error ? error.message : "ログアウトに失敗しました。");
  } finally {
    button.disabled = false;
  }
});

document.querySelector("#new-project-button").addEventListener("click", () => openProjectEditor());
document.querySelector("#new-build-log-button").addEventListener("click", () => openBuildLogEditor());

projectForm.addEventListener("submit", (event) => {
  event.preventDefault();
  void saveRecord(projectForm, projectDialog, "/projects", projectPayload(projectForm));
});

buildLogForm.addEventListener("submit", (event) => {
  event.preventDefault();
  void saveRecord(buildLogForm, buildLogDialog, "/build-logs", buildLogPayload(buildLogForm));
});

document.querySelectorAll("[data-close]").forEach((button) => {
  button.addEventListener("click", () => document.getElementById(button.dataset.close).close());
});

document.querySelectorAll("[data-tab]").forEach((button) => {
  button.addEventListener("click", () => {
    const showProjects = button.dataset.tab === "projects";
    document.querySelector("#projects-section").hidden = !showProjects;
    document.querySelector("#build-logs-section").hidden = showProjects;
    document.querySelectorAll("[data-tab]").forEach((tab) => {
      tab.classList.toggle("active", tab === button);
      tab.setAttribute("aria-current", tab === button ? "page" : "false");
    });
  });
});

request("/session")
  .then(async (session) => {
    if (typeof session?.authenticated !== "boolean") {
      throw new Error("セッションAPIから想定外の応答が返されました。");
    }
    if (!session.authenticated) {
      showLogin();
      return;
    }
    showAdmin(session.username ?? "");
    await loadRecords();
  })
  .catch((error) => {
    showLogin(error instanceof Error ? error.message : "セッションを確認できませんでした。");
  });
