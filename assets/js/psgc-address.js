class PSGCAddress {
    constructor(config) {
        this.prefix = config.prefix || '';
        this.apiUrl = config.apiUrl || '/api/psgc-api.php';
        this.regionSelect = document.getElementById(`${this.prefix}region`);
        this.provinceSelect = document.getElementById(`${this.prefix}province`);
        this.citySelect = document.getElementById(`${this.prefix}city`);
        this.barangaySelect = document.getElementById(`${this.prefix}barangay`);
        
        // Hidden inputs for storing names
        this.regionNameInput = document.getElementById(`${this.prefix}region_name`);
        this.provinceNameInput = document.getElementById(`${this.prefix}province_name`);
        this.cityNameInput = document.getElementById(`${this.prefix}city_name`);
        this.barangayNameInput = document.getElementById(`${this.prefix}barangay_name`);
        
        this.onRegionChange = config.onRegionChange || null;
        this.onProvinceChange = config.onProvinceChange || null;
        this.onCityChange = config.onCityChange || null;
        this.onBarangayChange = config.onBarangayChange || null;
        
        this.init();
    }

    init() {
        this.loadRegions();
        this.attachEventListeners();
    }

    attachEventListeners() {
        if (this.regionSelect) {
            this.regionSelect.addEventListener('change', () => this.handleRegionChange());
        }
        
        if (this.provinceSelect) {
            this.provinceSelect.addEventListener('change', () => this.handleProvinceChange());
        }
        
        if (this.citySelect) {
            this.citySelect.addEventListener('change', () => this.handleCityChange());
        }
        
        if (this.barangaySelect) {
            this.barangaySelect.addEventListener('change', () => this.handleBarangayChange());
        }
    }

    async loadRegions() {
        try {
            this.setLoading(this.regionSelect, true);
            const response = await fetch(`${this.apiUrl}?action=regions`);
            const result = await response.json();
            
            if (result.success && result.data) {
                this.populateSelect(this.regionSelect, result.data, 'Select Region');
            } else {
                console.error('Failed to load regions:', result.error);
                this.showError(this.regionSelect, 'Failed to load regions');
            }
        } catch (error) {
            console.error('Error loading regions:', error);
            this.showError(this.regionSelect, 'Error loading regions');
        } finally {
            this.setLoading(this.regionSelect, false);
        }
    }

    async handleRegionChange() {
        const regionCode = this.regionSelect.value;
        
        this.resetSelect(this.provinceSelect, 'Select Region First');
        this.resetSelect(this.citySelect, 'Select Province First');
        this.resetSelect(this.barangaySelect, 'Select City First');
        
        // Clear hidden name inputs
        if (this.provinceNameInput) this.provinceNameInput.value = '';
        if (this.cityNameInput) this.cityNameInput.value = '';
        if (this.barangayNameInput) this.barangayNameInput.value = '';
        
        if (!regionCode) {
            if (this.regionNameInput) this.regionNameInput.value = '';
            if (this.onRegionChange) this.onRegionChange(null);
            return;
        }

        const selectedOption = this.regionSelect.options[this.regionSelect.selectedIndex];
        const regionData = {
            code: regionCode,
            name: selectedOption.text
        };
        
        // Update hidden input with name
        if (this.regionNameInput) {
            this.regionNameInput.value = regionData.name;
        }
        
        if (this.onRegionChange) this.onRegionChange(regionData);

        // Check if NCR - load cities directly
        if (this.isNCR(regionCode)) {
            await this.loadCities(regionCode, true);
            if (this.provinceSelect) {
                this.provinceSelect.disabled = true;
                this.provinceSelect.innerHTML = '<option value="">Not Applicable (NCR)</option>';
                if (this.provinceNameInput) {
                    this.provinceNameInput.value = 'National Capital Region (NCR)';
                }
            }
        } else {
            if (this.provinceSelect) {
                this.provinceSelect.disabled = false;
            }
            await this.loadProvinces(regionCode);
        }
    }

    isNCR(code) {
        // NCR region code
        return code === '1300000000' || code === '13';
    }

    async loadProvinces(regionCode) {
        if (!this.provinceSelect) return;

        try {
            this.setLoading(this.provinceSelect, true);
            const response = await fetch(`${this.apiUrl}?action=provinces&region_code=${regionCode}`);
            const result = await response.json();
            
            if (result.success && result.data) {
                this.populateSelect(this.provinceSelect, result.data, 'Select Province');
            } else {
                console.error('Failed to load provinces:', result.error);
                this.showError(this.provinceSelect, 'Failed to load provinces');
            }
        } catch (error) {
            console.error('Error loading provinces:', error);
            this.showError(this.provinceSelect, 'Error loading provinces');
        } finally {
            this.setLoading(this.provinceSelect, false);
        }
    }

    async handleProvinceChange() {
        const provinceCode = this.provinceSelect.value;
        
        this.resetSelect(this.citySelect, 'Select Province First');
        this.resetSelect(this.barangaySelect, 'Select City First');
        
        // Clear hidden name inputs
        if (this.cityNameInput) this.cityNameInput.value = '';
        if (this.barangayNameInput) this.barangayNameInput.value = '';
        
        if (!provinceCode) {
            if (this.provinceNameInput) this.provinceNameInput.value = '';
            if (this.onProvinceChange) this.onProvinceChange(null);
            return;
        }

        const selectedOption = this.provinceSelect.options[this.provinceSelect.selectedIndex];
        const provinceData = {
            code: provinceCode,
            name: selectedOption.text
        };
        
        // Update hidden input with name
        if (this.provinceNameInput) {
            this.provinceNameInput.value = provinceData.name;
        }
        
        if (this.onProvinceChange) this.onProvinceChange(provinceData);

        await this.loadCities(provinceCode, false);
    }

    async loadCities(code, isRegion = false) {
        if (!this.citySelect) return;

        try {
            this.setLoading(this.citySelect, true);
            const response = await fetch(`${this.apiUrl}?action=cities&province_code=${code}`);
            const result = await response.json();
            
            if (result.success && result.data) {
                this.populateSelect(this.citySelect, result.data, 'Select City/Municipality');
            } else {
                console.error('Failed to load cities:', result.error);
                this.showError(this.citySelect, 'Failed to load cities');
            }
        } catch (error) {
            console.error('Error loading cities:', error);
            this.showError(this.citySelect, 'Error loading cities');
        } finally {
            this.setLoading(this.citySelect, false);
        }
    }

    async handleCityChange() {
        const cityCode = this.citySelect.value;
        
        this.resetSelect(this.barangaySelect, 'Select City First');
        
        // Clear hidden name input
        if (this.barangayNameInput) this.barangayNameInput.value = '';
        
        if (!cityCode) {
            if (this.cityNameInput) this.cityNameInput.value = '';
            if (this.onCityChange) this.onCityChange(null);
            return;
        }

        const selectedOption = this.citySelect.options[this.citySelect.selectedIndex];
        const cityData = {
            code: cityCode,
            name: selectedOption.text
        };
        
        // Update hidden input with name
        if (this.cityNameInput) {
            this.cityNameInput.value = cityData.name;
        }
        
        if (this.onCityChange) this.onCityChange(cityData);

        await this.loadBarangays(cityCode);
    }

    async loadBarangays(cityCode) {
        if (!this.barangaySelect) return;

        try {
            this.setLoading(this.barangaySelect, true);
            const response = await fetch(`${this.apiUrl}?action=barangays&city_code=${cityCode}`);
            const result = await response.json();
            
            if (result.success && result.data) {
                this.populateSelect(this.barangaySelect, result.data, 'Select Barangay');
            } else {
                console.error('Failed to load barangays:', result.error);
                this.showError(this.barangaySelect, 'Failed to load barangays');
            }
        } catch (error) {
            console.error('Error loading barangays:', error);
            this.showError(this.barangaySelect, 'Error loading barangays');
        } finally {
            this.setLoading(this.barangaySelect, false);
        }
    }

    handleBarangayChange() {
        const barangayCode = this.barangaySelect.value;
        
        if (!barangayCode) {
            if (this.barangayNameInput) this.barangayNameInput.value = '';
            if (this.onBarangayChange) this.onBarangayChange(null);
            return;
        }

        const selectedOption = this.barangaySelect.options[this.barangaySelect.selectedIndex];
        const barangayData = {
            code: barangayCode,
            name: selectedOption.text
        };
        
        // Update hidden input with name
        if (this.barangayNameInput) {
            this.barangayNameInput.value = barangayData.name;
        }
        
        if (this.onBarangayChange) this.onBarangayChange(barangayData);
    }

    populateSelect(selectElement, data, placeholder) {
        if (!selectElement) return;

        selectElement.innerHTML = `<option value="">${placeholder}</option>`;
        
        if (Array.isArray(data)) {
            data.forEach(item => {
                const option = document.createElement('option');
                option.value = item.code;
                option.textContent = item.name;
                selectElement.appendChild(option);
            });
        }
        
        selectElement.disabled = false;
    }

    resetSelect(selectElement, placeholder) {
        if (!selectElement) return;

        selectElement.innerHTML = `<option value="">${placeholder}</option>`;
        selectElement.disabled = true;
    }

    setLoading(selectElement, isLoading) {
        if (!selectElement) return;

        if (isLoading) {
            selectElement.disabled = true;
            selectElement.innerHTML = '<option value="">Loading...</option>';
        }
    }

    showError(selectElement, message) {
        if (!selectElement) return;

        selectElement.innerHTML = `<option value="">${message}</option>`;
        selectElement.disabled = true;
    }

    // Helper function to find option by text
    findOptionByText(selectElement, text) {
        if (!selectElement || !text) return null;
        
        const options = selectElement.options;
        for (let i = 0; i < options.length; i++) {
            if (options[i].text === text) {
                return options[i].value;
            }
        }
        return null;
    }

    async setValuesByName(names) {
        console.log('Setting values by name:', names);
        
        // Load regions first
        await this.loadRegions();
        
        if (names.region) {
            // Wait for regions to be loaded
            await new Promise(resolve => setTimeout(resolve, 300));
            
            // Find the region option by name
            const regionCode = this.findOptionByText(this.regionSelect, names.region);
            if (regionCode) {
                this.regionSelect.value = regionCode;
                await this.handleRegionChange();
            }
        }

        if (names.province && !this.isNCR(this.regionSelect.value)) {
            await new Promise(resolve => setTimeout(resolve, 500));
            const provinceCode = this.findOptionByText(this.provinceSelect, names.province);
            if (provinceCode) {
                this.provinceSelect.value = provinceCode;
                await this.handleProvinceChange();
            }
        }

        if (names.city) {
            await new Promise(resolve => setTimeout(resolve, 500));
            const cityCode = this.findOptionByText(this.citySelect, names.city);
            if (cityCode) {
                this.citySelect.value = cityCode;
                await this.handleCityChange();
            }
        }

        if (names.barangay) {
            await new Promise(resolve => setTimeout(resolve, 500));
            const barangayCode = this.findOptionByText(this.barangaySelect, names.barangay);
            if (barangayCode) {
                this.barangaySelect.value = barangayCode;
                this.handleBarangayChange();
            }
        }
    }

    async setValues(values) {
        // Support both code-based and name-based setting
        if (values.region && values.region.length === 10) {
            // Looks like a code
            await this.setValuesByCode(values);
        } else {
            // Looks like a name
            await this.setValuesByName(values);
        }
    }

    async setValuesByCode(values) {
        if (values.region) {
            await this.loadRegions();
            await new Promise(resolve => setTimeout(resolve, 300));
            this.regionSelect.value = values.region;
            await this.handleRegionChange();
        }

        if (values.province && !this.isNCR(values.region)) {
            await new Promise(resolve => setTimeout(resolve, 500));
            this.provinceSelect.value = values.province;
            await this.handleProvinceChange();
        }

        if (values.city) {
            await new Promise(resolve => setTimeout(resolve, 500));
            this.citySelect.value = values.city;
            await this.handleCityChange();
        }

        if (values.barangay) {
            await new Promise(resolve => setTimeout(resolve, 500));
            this.barangaySelect.value = values.barangay;
            this.handleBarangayChange();
        }
    }

    getValues() {
        return {
            region: this.regionSelect?.value || '',
            regionName: this.regionSelect?.options[this.regionSelect.selectedIndex]?.text || '',
            province: this.provinceSelect?.value || '',
            provinceName: this.provinceSelect?.options[this.provinceSelect.selectedIndex]?.text || '',
            city: this.citySelect?.value || '',
            cityName: this.citySelect?.options[this.citySelect.selectedIndex]?.text || '',
            barangay: this.barangaySelect?.value || '',
            barangayName: this.barangaySelect?.options[this.barangaySelect.selectedIndex]?.text || ''
        };
    }

    reset() {
        if (this.regionSelect) {
            this.regionSelect.value = '';
        }
        if (this.regionNameInput) this.regionNameInput.value = '';
        if (this.provinceNameInput) this.provinceNameInput.value = '';
        if (this.cityNameInput) this.cityNameInput.value = '';
        if (this.barangayNameInput) this.barangayNameInput.value = '';
        
        this.resetSelect(this.provinceSelect, 'Select Region First');
        this.resetSelect(this.citySelect, 'Select Province First');
        this.resetSelect(this.barangaySelect, 'Select City First');
    }
}