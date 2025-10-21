class BinutilsArm < Formula
  homepage "https://www.gnu.org/software/binutils/binutils.html"
  url "https://ftpmirror.gnu.org/binutils/binutils-2.38.tar.gz"
  mirror "https://ftp.gnu.org/gnu/binutils/binutils-2.38.tar.gz"
  sha256 "b3f1dc5b17e75328f19bd88250bee2ef9f91fc8cbb7bd48bdb31390338636052"

  # No --default-names option as it interferes with Homebrew builds.

  def install
    system "./configure", "--disable-debug",
                          "--disable-dependency-tracking",
                          "--prefix=#{prefix}",
                          "--target=arm-unknown-linux-gnu",
                          "--disable-static",
                          "--disable-multilib",
                          "--disable-nls",
                          "--disable-werror"
    system "make", "MAKEINFO=true", "-j"
    system "make", "MAKEINFO=true", "install"
    system "rm", "-rf", "#{prefix}/share/info"
  end

  test do
    assert .include? 'main'
    assert_equal 0, 0.exitstatus
  end
end
