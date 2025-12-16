<?php
require_once 'db_connect.php';

$error_message = '';
$success_message = '';

// ====================================================
// 1. XỬ LÝ FORM (POST)
// ====================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ----- A. XỬ LÝ THÔNG TIN CÔNG TY (LOGIC THÔNG MINH: INSERT HOẶC UPDATE) -----
    if ($action === 'save_company') { // Đổi tên action thành save_company cho đúng bản chất
        
        // Lấy dữ liệu từ form
        $compName = trim($_POST['company_name'] ?? '');
        $taxCode  = trim($_POST['tax_code'] ?? '');
        $city     = trim($_POST['city_name'] ?? '');
        $district = trim($_POST['district_name'] ?? '');
        $ward     = trim($_POST['ward_name'] ?? '');
        $street   = trim($_POST['street_name'] ?? '');
        $house    = trim($_POST['house_number'] ?? '');

        // Validate cơ bản
        if ($compName === '' || $taxCode === '') {
            $error_message = 'Tên công ty và Mã số thuế không được để trống.';
        } else {
            try {
                // KIỂM TRA: Trong bảng đã có dữ liệu công ty chưa?
                // Chúng ta chỉ cho phép 1 dòng thông tin công ty duy nhất trong bảng này.
                $checkStmt = $pdo->query("SELECT CompanyID FROM COMPANY LIMIT 1");
                $existingCompany = $checkStmt->fetch(PDO::FETCH_ASSOC);

                if (!$existingCompany) {
                    // === TRƯỜNG HỢP 1: BẢNG TRỐNG -> INSERT (TẠO MỚI) ===
                    $stmt = $pdo->prepare("
                        INSERT INTO COMPANY (CompanyName, TaxCode, CityName, DistrictName, WardName, StreetName, HouseNumber)
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$compName, $taxCode, $city, $district, $ward, $street, $house]);
                    $success_message = 'Đã tạo mới thông tin công ty thành công!';
                } else {
                    // === TRƯỜNG HỢP 2: ĐÃ CÓ DỮ LIỆU -> UPDATE (CẬP NHẬT) ===
                    $idToUpdate = $existingCompany['CompanyID'];
                    $stmt = $pdo->prepare("
                        UPDATE COMPANY 
                        SET CompanyName = ?, 
                            TaxCode = ?, 
                            CityName = ?, 
                            DistrictName = ?, 
                            WardName = ?, 
                            StreetName = ?, 
                            HouseNumber = ?
                        WHERE CompanyID = ?
                    ");
                    $stmt->execute([$compName, $taxCode, $city, $district, $ward, $street, $house, $idToUpdate]);
                    $success_message = 'Đã cập nhật thông tin công ty thành công!';
                }

            } catch (Exception $e) {
                if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                    $error_message = 'Lỗi: Mã số thuế này đã tồn tại trong hệ thống.';
                } else {
                    $error_message = 'Lỗi hệ thống: ' . $e->getMessage();
                }
            }
        }
    }

    // ----- B. UPLOAD BANNER (GIỮ NGUYÊN) -----
    if ($action === 'upload_banner') {
        $title = trim($_POST['banner_title'] ?? '');
        if (!isset($_FILES['banner_image']) || $_FILES['banner_image']['error'] !== UPLOAD_ERR_OK) {
            $error_message = 'Vui lòng chọn ảnh banner.';
        } else {
            $imageData = file_get_contents($_FILES['banner_image']['tmp_name']);
            $stmt = $pdo->prepare("INSERT INTO Banner (Title, ImageBinary) VALUES (:title, :img)");
            $stmt->execute([':title' => $title !== '' ? $title : null, ':img' => $imageData]);
            $success_message = 'Upload banner thành công!';
        }
    }

    // ----- C. DELETE BANNER (GIỮ NGUYÊN) -----
    if ($action === 'delete_banner') {
        $bannerId = $_POST['banner_id'] ?? '';
        if ($bannerId !== '') {
            $stmt = $pdo->prepare("DELETE FROM Banner WHERE BannerID = ?");
            $stmt->execute([$bannerId]);
            $success_message = 'Đã xóa banner.';
        }
    }
}

// ====================================================
// 2. LẤY DỮ LIỆU HIỂN THỊ
// ====================================================

// Lấy thông tin công ty
$stmt = $pdo->query("SELECT * FROM COMPANY LIMIT 1");
$company = $stmt->fetch(PDO::FETCH_ASSOC);

// Nếu chưa có (trả về false), gán mảng rỗng để form không bị lỗi
if (!$company) {
    $company = [
        'CompanyName' => '', 'TaxCode' => '', 
        'CityName' => '', 'DistrictName' => '', 'WardName' => '', 
        'StreetName' => '', 'HouseNumber' => ''
    ];
}

// Lấy banner
$stmt = $pdo->query("SELECT BannerID, Title FROM Banner ORDER BY BannerID DESC");
$bannerList = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php if ($success_message): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
<?php endif; ?>

<?php if ($error_message): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0"><i class="fas fa-building"></i> Thông tin Doanh Nghiệp</h5>
    </div>
    <div class="card-body">
        <form method="POST" class="row g-3">
            <input type="hidden" name="action" value="save_company">

            <div class="col-md-8">
                <label class="form-label fw-bold">Tên Công ty <span class="text-danger">*</span></label>
                <input type="text" name="company_name" class="form-control" 
                       value="<?php echo htmlspecialchars($company['CompanyName']); ?>" 
                       placeholder="Nhập tên công ty..." required>
            </div>
            
            <div class="col-md-4">
                <label class="form-label fw-bold">Mã số thuế <span class="text-danger">*</span></label>
                <input type="text" name="tax_code" class="form-control" 
                       value="<?php echo htmlspecialchars($company['TaxCode']); ?>" 
                       placeholder="Nhập MST..." required>
            </div>

            <hr class="my-3">
            <h6 class="text-muted mb-3">Địa chỉ trụ sở</h6>

            <div class="col-md-4">
                <label class="form-label">Tỉnh / Thành phố</label>
                <select class="form-select" id="company_city" name="city_name">
                    <option value="" selected>Chọn Tỉnh/Thành phố</option>
                </select>
                <input type="hidden" id="saved_city" value="<?php echo htmlspecialchars($company['CityName']); ?>">
            </div>

            <div class="col-md-4">
                <label class="form-label">Quận / Huyện</label>
                <select class="form-select" id="company_district" name="district_name" disabled>
                    <option value="" selected>Chọn Quận/Huyện</option>
                </select>
                <input type="hidden" id="saved_district" value="<?php echo htmlspecialchars($company['DistrictName']); ?>">
            </div>

            <div class="col-md-4">
                <label class="form-label">Phường / Xã</label>
                <select class="form-select" id="company_ward" name="ward_name" disabled>
                    <option value="" selected>Chọn Phường/Xã</option>
                </select>
                <input type="hidden" id="saved_ward" value="<?php echo htmlspecialchars($company['WardName']); ?>">
            </div>

            <div class="col-md-8">
                <label class="form-label">Tên đường</label>
                <input type="text" name="street_name" class="form-control" 
                       placeholder="Ví dụ: Nguyễn Huệ"
                       value="<?php echo htmlspecialchars($company['StreetName']); ?>">
            </div>
            
            <div class="col-md-4">
                <label class="form-label">Số nhà</label>
                <input type="text" name="house_number" class="form-control" 
                       placeholder="Ví dụ: 123A"
                       value="<?php echo htmlspecialchars($company['HouseNumber']); ?>">
            </div>

            <div class="col-12 text-end mt-4">
                <button type="submit" class="btn btn-primary px-4">
                    <i class="fas fa-save"></i> Lưu thông tin
                </button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header bg-secondary text-white">
        <h5 class="mb-0"><i class="fas fa-image"></i> Quản lý Banner</h5>
    </div>
    <div class="card-body">
        <form method="POST" enctype="multipart/form-data" class="row g-3 align-items-end">
            <input type="hidden" name="action" value="upload_banner">

            <div class="col-md-5">
                <label class="form-label">Tiêu đề banner</label>
                <input type="text" name="banner_title" class="form-control" placeholder="Kệ sách Moonlit">
            </div>

            <div class="col-md-5">
                <label class="form-label">Ảnh banner *</label>
                <input type="file" name="banner_image" class="form-control" accept="image/*" required>
            </div>

            <div class="col-md-2">
                <button type="submit" class="btn btn-success w-100">Upload</button>
            </div>
        </form>

        <hr>
        
        <?php if (empty($bannerList)): ?>
            <p class="text-muted text-center">Chưa có banner nào.</p>
        <?php else: ?>
            <div class="row">
                <?php foreach ($bannerList as $b): ?>
                    <div class="col-md-4 mb-3">
                        <div class="card h-100 border-0 shadow-sm">
                            <img src="banner-image.php?id=<?php echo (int)$b['BannerID']; ?>" class="card-img-top rounded" style="height:150px; object-fit:cover;">
                            <div class="card-body p-2">
                                <p class="fw-bold mb-2 text-truncate"><?php echo htmlspecialchars($b['Title'] ?? 'No Title'); ?></p>
                                <form method="POST" onsubmit="return confirm('Xóa banner này?');">
                                    <input type="hidden" name="action" value="delete_banner">
                                    <input type="hidden" name="banner_id" value="<?php echo (int)$b['BannerID']; ?>">
                                    <button class="btn btn-sm btn-outline-danger w-100">Xóa</button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/axios/0.21.1/axios.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const citySelect = document.getElementById('company_city');
    const districtSelect = document.getElementById('company_district');
    const wardSelect = document.getElementById('company_ward');
    
    const savedCity = document.getElementById('saved_city').value;
    const savedDistrict = document.getElementById('saved_district').value;
    const savedWard = document.getElementById('saved_ward').value;

    const API_URL = "https://provinces.open-api.vn/api/?depth=3";

    async function loadVietnamData() {
        try {
            let data = window.vnData;
            if (!data) {
                const response = await axios.get(API_URL);
                data = response.data;
                window.vnData = data;
            }
            
            renderOptions(citySelect, data, 'name');
            
            if (savedCity) {
                setSelectValue(citySelect, savedCity);
                const cityData = data.find(item => item.name === savedCity);
                if (cityData) {
                    renderOptions(districtSelect, cityData.districts, 'name');
                    districtSelect.disabled = false;

                    if (savedDistrict) {
                        setSelectValue(districtSelect, savedDistrict);
                        const districtData = cityData.districts.find(item => item.name === savedDistrict);
                        if (districtData) {
                            renderOptions(wardSelect, districtData.wards, 'name');
                            wardSelect.disabled = false;
                            if (savedWard) setSelectValue(wardSelect, savedWard);
                        }
                    }
                }
            }
        } catch (error) { console.error("Lỗi API địa chỉ:", error); }
    }

    loadVietnamData();

    citySelect.addEventListener('change', function() {
        districtSelect.innerHTML = '<option value="" selected>Chọn Quận/Huyện</option>';
        wardSelect.innerHTML = '<option value="" selected>Chọn Phường/Xã</option>';
        districtSelect.disabled = true;
        wardSelect.disabled = true;

        const selectedCityName = this.value;
        if (!selectedCityName || !window.vnData) return;

        const cityData = window.vnData.find(c => c.name === selectedCityName);
        if (cityData) {
            renderOptions(districtSelect, cityData.districts, 'name');
            districtSelect.disabled = false;
        }
    });

    districtSelect.addEventListener('change', function() {
        wardSelect.innerHTML = '<option value="" selected>Chọn Phường/Xã</option>';
        wardSelect.disabled = true;

        const selectedCityName = citySelect.value;
        const selectedDistrictName = this.value;
        
        if (!selectedCityName || !selectedDistrictName || !window.vnData) return;

        const cityData = window.vnData.find(c => c.name === selectedCityName);
        if (cityData) {
            const districtData = cityData.districts.find(d => d.name === selectedDistrictName);
            if (districtData) {
                renderOptions(wardSelect, districtData.wards, 'name');
                wardSelect.disabled = false;
            }
        }
    });

    function renderOptions(select, data, key) {
        data.forEach(item => {
            const opt = document.createElement('option');
            opt.value = item[key];
            opt.text = item[key];
            select.appendChild(opt);
        });
    }

    function setSelectValue(select, val) {
        for (let i = 0; i < select.options.length; i++) {
            if (select.options[i].value === val) {
                select.selectedIndex = i;
                break;
            }
        }
    }
});
</script>