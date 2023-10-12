'use server';

import googleClassroom from '@/lib/config/googleClassroom';

interface CourseWork {
  courseId: string;
  title: string;
  description: string;
}

export const createWork = async (courseWork: CourseWork) => {
  const { courseId, title, description } = courseWork;
  const classroom = await googleClassroom();

  const res = await classroom.courses.courseWork.create({
    courseId: courseId,
    requestBody: {
      courseId: courseId,
      title: title,
      description: description,
      workType: 'ASSIGNMENT',
      state: 'PUBLISHED',
      maxPoints: 100,
      associatedWithDeveloper: true,
      materials: [
        {
          link: {
            url: 'https://classroom.google.com/c/MTIwMzU2NzQyNzY4/a/MTIwMzU2NzQyNzY5/details',
          },
        },
      ],
    },
  });

  return res;
};
