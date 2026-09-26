<section class="login-screen" id="loginScreen">
    <div class="login-card">
      <div class="login-logo-wrap"><img class="login-logo" src="<?= base_url('assets/logo-gold.png') ?>" alt="AIWalas"></div>
      <div class="eyebrow">Portal kelas 11PF1</div>
      <h1>Selamat datang kembali</h1>
      <p class="login-subtitle">Masuk untuk mengakses workspace AIWalas.</p>
      <form id="loginForm">
        <label>Masuk sebagai
          <select id="loginRole">
            <option value="walas">Wali Kelas</option>
            <option value="assistant">Sekretaris Kelas</option>
            <option value="student">Siswa</option>
            <option value="teacher">Guru</option>

          </select>
        </label>
        <label>Username
          <input id="loginUsername" autocomplete="username" placeholder="Masukkan username" required>
        </label>
        <label>Password
          <input id="loginPassword" type="password" autocomplete="current-password" placeholder="Masukkan password" required>
        </label>
        <button class="primary-btn login-submit" type="submit">Masuk ke AIWalas</button>
        <p class="login-error" id="loginError" role="alert"></p>
      </form>
      <div class="login-note" id="loginNote">Siswa menggunakan NIS sebagai username dan password.</div>
    </div>
  </section>