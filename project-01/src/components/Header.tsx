const Header = () => {
  return (
    <header className="container">
      <div className="brand_logo">
        <img src="/images/brand_logo.png" alt="Logo" />
      </div>
      <ul>
        <li>Menu</li>
        <li>Location</li>
        <li>About</li>
        <li>Contact</li>
      </ul>
      <button>Login</button>
    </header>
  );
};

export default Header;
