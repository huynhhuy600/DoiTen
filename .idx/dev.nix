# To learn more about how to use Nix to configure your environment
# see: https://developers.google.com/idx/guides/customize-idx-env
{ pkgs, ... }: {
  # Which nixpkgs channel to use.
  channel = "stable-23.11"; # or "unstable"
  
  # Use https://search.nixos.org/packages to find packages
  packages = [
    pkgs.php82
    pkgs.php82Packages.composer
    pkgs.ghostscript
    pkgs.tesseract
  ];
  
  # Sets environment variables in the workspace
  env = {
    OMP_NUM_THREADS = "0";
    OMP_THREAD_LIMIT = "0";
    # Đặt TESSDATA_PREFIX để Tesseract tìm thấy model ngôn ngữ
    TESSDATA_PREFIX = "/home/user/.local/share/tessdata";
  };
  
  idx = {
    # Search for the extensions you want on https://open-vsx.org/ and use "publisher.id"
    extensions = [
      "bmewburn.vscode-intelephense-client"
    ];
    
    workspace = {
      # Runs when a workspace is first created
      onCreate = {
        # Tải dữ liệu ngôn ngữ Tiếng Việt và Tiếng Anh cho Tesseract OCR (vì Nix mặc định không kèm ngôn ngữ)
        setup-tessdata = ''
          mkdir -p /home/user/.local/share/tessdata
          curl -sL https://github.com/tesseract-ocr/tessdata_fast/raw/main/vie.traineddata -o /home/user/.local/share/tessdata/vie.traineddata
          curl -sL https://github.com/tesseract-ocr/tessdata_fast/raw/main/eng.traineddata -o /home/user/.local/share/tessdata/eng.traineddata
        '';
      };
      
      # Runs when a workspace is (re)started
      onStart = {
        # Start command
      };
    };
    
    # Enable previews and customize configuration
    previews = {
      enable = true;
      previews = {
        web = {
          # Khởi chạy PHP server tích hợp sẵn, cấu hình tăng giới hạn bộ nhớ tương tự .htaccess
          command = [
            "php" "-S" "0.0.0.0:$PORT" 
            "-d" "upload_max_filesize=512M" 
            "-d" "post_max_size=512M" 
            "-d" "memory_limit=512M" 
            "-d" "max_execution_time=600" 
            "-d" "max_input_time=600" 
            "-t" "."
          ];
          manager = "web";
        };
      };
    };
  };
}
