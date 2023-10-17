'use client';
import WandButton from '@/app/core/WandButton';
import { classroom_v1 } from 'googleapis';
import React from 'react';
import { notifications } from '@mantine/notifications';
import axios from 'axios';

type Props = {
  course: classroom_v1.Schema$Course;
  title: string;
  setDescription: React.Dispatch<React.SetStateAction<string>>;
};

export default function GenerateCourseWork({
  course,
  title,
  setDescription,
}: Props) {
  const [loading, setLoading] = React.useState(false);

  const handleSubmit = async () => {
    setLoading(true);
    try {
      const { data } = await axios.post('/api/ai/coursework', {
        courseName: course.name,
        courseworkTitle: title,
      });

      if (data.error) {
        notifications.show({
          title: 'Error',
          color: 'red',
          autoClose: 1000 * 30, // 30 seconds
          message: data.error,
        });
      } else {
        setDescription(data.description);
      }
    } finally {
      setLoading(false);
    }
  };

  return (
    <WandButton disabled={!title} loading={loading} onClick={handleSubmit} />
  );
}
