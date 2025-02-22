library(httr)
library(jsonlite)
library(ggplot2)
library(dplyr)
library(lubridate)

#set your wd to whatever works in your setup
setwd("/src/Home/r")

# Set your BirdWeather API token
api_token <- Sys.getenv("BIRD_API")

# Define date ranges
start_date <- Sys.Date() - 7
month_start_date <- Sys.Date() - 30

# Function to make API calls
fetch_bird_data <- function(url) {
  response <- httr::GET(url, add_headers(Accept = "application/json"))
  if (http_error(response)) {
    stop(paste("API request failed:", http_status(response)$message))
  }
  content(response, "text", encoding = "UTF-8") %>%
    fromJSON() %>%
    .$species
}

# Fetch weekly and monthly bird data
urlw <- paste0("https://app.birdweather.com/api/v1/stations/", api_token, "/species?since=", start_date)
urlm <- paste0("https://app.birdweather.com/api/v1/stations/", api_token, "/species?since=", month_start_date)

bird_dataw <- fetch_bird_data(urlw)
bird_datam <- fetch_bird_data(urlm)

# Add detection counts
bird_dataw <- cbind(bird_dataw, bird_dataw$detections)
bird_datam <- cbind(bird_datam, bird_datam$detections)

# Transform and filter data
bird_w <- bird_dataw %>%
  select(species_id = id,
         common_name = commonName,
         scientific_name = scientificName,
         almost_certain = almostCertain,
         latest_detection = latestDetectionAt,
         image_url = imageUrl)

bird_m <- bird_datam %>%
  select(species_id = id,
         common_name = commonName,
         scientific_name = scientificName,
         almost_certain = almostCertain,
         latest_detection = latestDetectionAt,
         image_url = imageUrl)

# Identify new species
new_species <- bird_w %>%
  inner_join(bird_m, by = "species_id", suffix = c("_month", "_week")) %>%
  filter(almost_certain_month == almost_certain_week) %>%
  filter(almost_certain_week > 1, species_id != 355) %>%
  select(species_id, 
         common_name = common_name_week, 
         scientific_name = scientific_name_week,
         counts = almost_certain_week,
         image_url = image_url_week)
#weekly new species overwrites each week and saves to data file
write.csv(new_species, "../data/new_species.csv", row.names = FALSE)

# Plot bird detections
bird_w %>%
  filter(almost_certain > 1, species_id != 355) %>%
  arrange(almost_certain) %>%
  ggplot(aes(x = reorder(common_name, almost_certain), y = almost_certain, fill = almost_certain)) + 
  geom_bar(stat = "identity") +
  coord_flip() +
  scale_fill_gradient(low = "#56B1F7", high = "#132B43") +
  labs(title = "Number of Birds This Week (> 1)", 
       x = "Bird Names", 
       y = "# of Identifications") +
  theme_minimal() +
  theme(legend.title = element_blank())
# weekly file overwrites each week and saves in images folder
ggsave("../images/species_chart.png", scale = 1.5, width = 4, height = 3, units = "in", dpi = 200)

q(save = "no")

