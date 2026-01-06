<?php
// classes/CheckoutPDFGenerator.php
require_once __DIR__ . '/../vendor/autoload.php'; // Composer autoload for mPDF

use Mpdf\Mpdf;

class CheckoutPDFGenerator {
    private $conn;
    
    public function __construct($db_connection) {
        $this->conn = $db_connection;
    }
    
    /**
     * Generate equipment checkout PDF with e-signature
     */
    public function generateCheckoutPDF($borrower_name, $equipment_list, $expected_return_date, $purpose = '') {
        try {
            // Create mPDF instance
            $mpdf = new Mpdf([
                'format' => 'A4',
                'margin_left' => 15,
                'margin_right' => 15,
                'margin_top' => 20,
                'margin_bottom' => 20,
                'margin_header' => 10,
                'margin_footer' => 10
            ]);
            
            // Set PDF metadata
            $mpdf->SetTitle('Equipment Checkout Agreement - ' . $borrower_name);
            $mpdf->SetAuthor('Neofox Media Gear Control');
            $mpdf->SetSubject('Equipment Checkout Agreement');
            
            // Generate HTML content
            $html = $this->buildPDFContent($borrower_name, $equipment_list, $expected_return_date, $purpose);
            
            // Write HTML to PDF
            $mpdf->WriteHTML($html);
            
            // Generate filename
            $filename = 'checkout_agreement_' . preg_replace('/[^a-zA-Z0-9]/', '_', $borrower_name) . '_' . date('Y-m-d_H-i-s') . '.pdf';
            $filepath = 'uploads/checkout_agreements/' . $filename;
            
            // Ensure directory exists
            $dir = dirname($filepath);
            if (!file_exists($dir)) {
                mkdir($dir, 0755, true);
            }
            
            // Save PDF to file
            $mpdf->Output($filepath, 'F');
            
            // Also return PDF as string for email attachment
            $pdf_content = $mpdf->Output('', 'S');
            
            return [
                'success' => true,
                'filepath' => $filepath,
                'filename' => $filename,
                'pdf_content' => $pdf_content,
                'url' => $this->getPublicURL($filepath)
            ];
            
        } catch (Exception $e) {
            error_log('PDF Generation Error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Build the HTML content for the PDF
     */
    private function buildPDFContent($borrower_name, $equipment_list, $expected_return_date, $purpose) {
        $checkout_date = date('F j, Y \a\t g:i A');
        $return_date = date('F j, Y \a\t g:i A', strtotime($expected_return_date));
        $agreement_id = 'NF-' . date('Y') . '-' . sprintf('%04d', rand(1000, 9999));
        
        // Calculate total value (you may want to add pricing to your database)
        $total_items = count($equipment_list);
        
        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { 
                    font-family: Arial, sans-serif; 
                    font-size: 12px; 
                    line-height: 1.4; 
                    color: #333; 
                }
                .header { 
                    text-align: center; 
                    margin-bottom: 30px; 
                    border-bottom: 3px solid #FFD700; 
                    padding-bottom: 20px; 
                }
                .company-logo { 
                    font-size: 24px; 
                    font-weight: bold; 
                    color: #000; 
                    margin-bottom: 5px; 
                }
                .document-title { 
                    font-size: 18px; 
                    font-weight: bold; 
                    margin-top: 15px; 
                    color: #000; 
                }
                .agreement-info { 
                    background: #f8f9fa; 
                    padding: 15px; 
                    border-radius: 5px; 
                    margin: 20px 0; 
                }
                .info-row { 
                    display: flex; 
                    justify-content: space-between; 
                    margin-bottom: 8px; 
                }
                .label { 
                    font-weight: bold; 
                    color: #000; 
                }
                .equipment-table { 
                    width: 100%; 
                    border-collapse: collapse; 
                    margin: 20px 0; 
                }
                .equipment-table th { 
                    background: #000; 
                    color: #FFD700; 
                    padding: 12px 8px; 
                    text-align: left; 
                    font-weight: bold; 
                    border: 1px solid #000; 
                }
                .equipment-table td { 
                    padding: 10px 8px; 
                    border: 1px solid #ddd; 
                    vertical-align: top; 
                }
                .equipment-table tr:nth-child(even) { 
                    background: #f8f9fa; 
                }
                .terms-section { 
                    margin: 25px 0; 
                    page-break-inside: avoid; 
                }
                .terms-title { 
                    font-size: 14px; 
                    font-weight: bold; 
                    margin-bottom: 10px; 
                    color: #000; 
                    border-bottom: 1px solid #FFD700; 
                    padding-bottom: 5px; 
                }
                .terms-list { 
                    margin-left: 0; 
                    padding-left: 0; 
                }
                .terms-list li { 
                    margin-bottom: 8px; 
                    list-style-type: decimal; 
                    margin-left: 20px; 
                }
                .signature-section { 
                    margin-top: 40px; 
                    page-break-inside: avoid; 
                }
                .signature-box { 
                    border: 2px solid #000; 
                    padding: 15px; 
                    margin: 15px 0; 
                    min-height: 80px; 
                    background: #fff; 
                }
                .signature-label { 
                    font-weight: bold; 
                    margin-bottom: 10px; 
                }
                .signature-line { 
                    border-bottom: 1px solid #000; 
                    margin: 30px 0 10px 0; 
                    height: 1px; 
                }
                .footer { 
                    margin-top: 30px; 
                    text-align: center; 
                    font-size: 10px; 
                    color: #666; 
                    border-top: 1px solid #ddd; 
                    padding-top: 15px; 
                }
                .urgent-note { 
                    background: #fff3cd; 
                    border: 2px solid #ffc107; 
                    padding: 15px; 
                    border-radius: 5px; 
                    margin: 20px 0; 
                    text-align: center; 
                    font-weight: bold; 
                }
            </style>
        </head>
        <body>
            <!-- Header -->
            <div class="header">
                <div class="company-logo">🎬 NEOFOX MEDIA</div>
                <div style="font-size: 14px; color: #666;">Professional Equipment Rental & Management</div>
                <div class="document-title">EQUIPMENT CHECKOUT AGREEMENT</div>
                <div style="font-size: 12px; margin-top: 10px;">Agreement ID: <strong>' . $agreement_id . '</strong></div>
            </div>
            
            <!-- Agreement Information -->
            <div class="agreement-info">
                <div class="info-row">
                    <span><span class="label">Borrower Name:</span> ' . htmlspecialchars($borrower_name) . '</span>
                    <span><span class="label">Checkout Date:</span> ' . $checkout_date . '</span>
                </div>
                <div class="info-row">
                    <span><span class="label">Expected Return:</span> ' . $return_date . '</span>
                    <span><span class="label">Total Items:</span> ' . $total_items . '</span>
                </div>';
        
        if (!empty($purpose)) {
            $html .= '<div style="margin-top: 10px;"><span class="label">Purpose:</span> ' . htmlspecialchars($purpose) . '</div>';
        }
        
        $html .= '
            </div>
            
            <!-- Equipment List -->
            <table class="equipment-table">
                <thead>
                    <tr>
                        <th style="width: 15%;">Asset ID</th>
                        <th style="width: 35%;">Equipment Name</th>
                        <th style="width: 20%;">Category</th>
                        <th style="width: 15%;">Condition</th>
                        <th style="width: 15%;">Serial Number</th>
                    </tr>
                </thead>
                <tbody>';
        
        foreach ($equipment_list as $item) {
            $html .= '
                    <tr>
                        <td><strong>' . htmlspecialchars($item['asset_id']) . '</strong></td>
                        <td>' . htmlspecialchars($item['asset_name']) . '</td>
                        <td>' . htmlspecialchars($item['category']) . '</td>
                        <td>' . htmlspecialchars($item['condition_status'] ?? 'Good') . '</td>
                        <td>' . htmlspecialchars($item['serial_number'] ?? 'N/A') . '</td>
                    </tr>';
        }
        
        $html .= '
                </tbody>
            </table>
            
            <!-- Urgent Return Notice -->
            <div class="urgent-note">
                ⚠️ IMPORTANT: All equipment must be returned by the expected return date in the same condition as received.
            </div>
            
            <!-- Terms and Conditions -->
            <div class="terms-section">
                <div class="terms-title">TERMS AND CONDITIONS</div>
                <ol class="terms-list">
                    <li><strong>Equipment Care:</strong> The borrower agrees to use all equipment with proper care and in accordance with manufacturer guidelines.</li>
                    <li><strong>Damage Responsibility:</strong> The borrower is fully responsible for any damage, loss, or theft of equipment while in their possession.</li>
                    <li><strong>Return Condition:</strong> All equipment must be returned in the same working condition as received, normal wear excepted.</li>
                    <li><strong>Late Returns:</strong> Late returns may result in additional fees and restricted access to future equipment loans.</li>
                    <li><strong>Replacement Costs:</strong> Lost or damaged equipment will be charged at current replacement value plus administrative fees.</li>
                    <li><strong>Authorized Use Only:</strong> Equipment is for authorized personnel only and may not be sub-rented or transferred to third parties.</li>
                    <li><strong>Insurance:</strong> The borrower acknowledges that equipment is covered under Neofox Media\'s insurance policy, subject to deductible charges.</li>
                    <li><strong>Technical Support:</strong> Contact the equipment manager immediately if any technical issues arise during use.</li>
                </ol>
            </div>
            
            <!-- Signature Section -->
            <div class="signature-section">
                <div style="font-weight: bold; margin-bottom: 20px; font-size: 14px;">ACKNOWLEDGMENT AND SIGNATURE</div>
                
                <div class="signature-box">
                    <div class="signature-label">Borrower Signature:</div>
                    <div style="margin: 20px 0;">
                        I, ' . htmlspecialchars($borrower_name) . ', acknowledge that I have received the above equipment in good working condition and agree to all terms and conditions stated in this agreement.
                    </div>
                    <div class="signature-line"></div>
                    <div style="display: flex; justify-content: space-between; margin-top: 10px;">
                        <span>Signature: ______________________________</span>
                        <span>Date: ' . date('m/d/Y') . '</span>
                    </div>
                </div>
                
                <div class="signature-box">
                    <div class="signature-label">Equipment Manager Signature:</div>
                    <div style="margin: 20px 0;">
                        I confirm that the equipment listed above has been checked out to the borrower in the condition stated.
                    </div>
                    <div class="signature-line"></div>
                    <div style="display: flex; justify-content: space-between; margin-top: 10px;">
                        <span>Signature: ______________________________</span>
                        <span>Date: ' . date('m/d/Y') . '</span>
                    </div>
                </div>
            </div>
            
            <!-- Footer -->
            <div class="footer">
                <p><strong>Neofox Media Equipment Management System</strong></p>
                <p>For questions or issues, contact: team@neofoxmedia.com | Emergency: +1 (XXX) XXX-XXXX</p>
                <p>Generated on ' . date('F j, Y \a\t g:i A') . ' | Document ID: ' . $agreement_id . '</p>
            </div>
        </body>
        </html>';
        
        return $html;
    }
    
    /**
     * Get public URL for PDF file
     */
    private function getPublicURL($filepath) {
        $base_url = 'https://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']);
        return $base_url . '/' . $filepath;
    }
}
?>